<?php

namespace App\Services;

use App\Enums\EndorsementMode;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\OvertimeAttachmentReportStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\Payroll\OvertimeRequestSyncService;
use App\Models\OvertimeAttachmentActivity;
use App\Models\OvertimeAttachmentApprovalAction;
use App\Models\OvertimeAttachmentApprovalAssignment;
use App\Models\OvertimeAttachmentReport;
use App\Models\User;
use App\Support\DepartmentApprovalScope;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeAttachmentReportService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OvertimeRequestSyncService $overtimeSync,
    ) {}

    public function saveDraft(
        Employee $employee,
        User $actor,
        array $data,
        ?OvertimeAttachmentReport $existing = null,
    ): OvertimeAttachmentReport {
        $overtimeRequest = $this->resolveOvertimeRequest($employee, $data, $existing);
        $payload = $this->validatedPayload($data, false, $existing, $overtimeRequest);

        return DB::transaction(function () use ($employee, $actor, $payload, $data, $existing, $overtimeRequest) {
            if ($existing) {
                $this->assertEmployeeOwns($existing, $employee);
                if (! $existing->canBeEditedByEmployee()) {
                    throw ValidationException::withMessages(['report' => 'This report can no longer be edited.']);
                }
                $existing->update($payload);
                $report = $existing;
            } else {
                $this->assertNoDuplicateReport($overtimeRequest);
                $report = OvertimeAttachmentReport::query()->create(array_merge($payload, [
                    'report_no' => $this->nextNumber(),
                    'overtime_request_id' => $overtimeRequest->id,
                    'employee_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'status' => OvertimeAttachmentReportStatus::Draft,
                    'submitted_by' => $actor->id,
                ]));
            }

            $this->syncActivities($report, $data['activities'] ?? [], false);
            $this->audit->log($actor, 'overtime_attachment_report_saved', 'OvertimeAttachmentReport', $report->id, "Draft {$report->report_no} saved.");

            return $report->fresh(['employee.department', 'overtimeRequest', 'activities']);
        });
    }

    public function submit(
        Employee $employee,
        User $actor,
        array $data,
        ?OvertimeAttachmentReport $existing = null,
    ): OvertimeAttachmentReport {
        $overtimeRequest = $this->resolveOvertimeRequest($employee, $data, $existing);
        $payload = $this->validatedPayload($data, true, $existing, $overtimeRequest);

        return DB::transaction(function () use ($employee, $actor, $payload, $data, $existing, $overtimeRequest) {
            $now = ManilaTime::now();

            if ($existing) {
                $this->assertEmployeeOwns($existing, $employee);
                if (! $existing->canBeEditedByEmployee()) {
                    throw ValidationException::withMessages(['report' => 'This report can no longer be submitted.']);
                }
                $existing->update(array_merge($payload, [
                    'submitted_at' => $now,
                    'submitted_by' => $actor->id,
                    'date_submitted' => ($payload['date_submitted'] ?? null) ?: $now->toDateString(),
                    'returned_at' => null,
                    'rejected_at' => null,
                    'status' => OvertimeAttachmentReportStatus::Submitted,
                    'current_stage' => null,
                    'review_notes' => null,
                ]));
                $report = $existing->fresh();
            } else {
                $this->assertNoDuplicateReport($overtimeRequest);
                $report = OvertimeAttachmentReport::query()->create(array_merge($payload, [
                    'report_no' => $this->nextNumber(),
                    'overtime_request_id' => $overtimeRequest->id,
                    'employee_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'submitted_at' => $now,
                    'submitted_by' => $actor->id,
                    'date_submitted' => ($payload['date_submitted'] ?? null) ?: $now->toDateString(),
                    'status' => OvertimeAttachmentReportStatus::Submitted,
                    'current_stage' => null,
                ]));
            }

            $this->syncActivities($report, $data['activities'] ?? [], true);

            $this->recordAction(
                $report,
                $actor,
                LeaveApprovalStage::CeoFinalApproval,
                'submitted',
                null,
                OvertimeAttachmentReportStatus::Draft,
                OvertimeAttachmentReportStatus::Submitted,
                'OT Attachment report submitted for payroll review.'
            );
            $this->audit->log($actor, 'overtime_attachment_report_submitted', 'OvertimeAttachmentReport', $report->id, "{$employee->fullName()} submitted {$report->report_no}.");

            return $report->fresh(['employee.department', 'overtimeRequest', 'activities', 'actions.user']);
        });
    }

    public function decideByPayrollAdmin(
        OvertimeAttachmentReport $report,
        User $actor,
        string $decision,
        ?string $notes = null,
    ): OvertimeAttachmentReport {
        if (! $report->pendingPayrollReview()) {
            throw ValidationException::withMessages(['decision' => 'This attachment report is not awaiting payroll review.']);
        }

        $notes = trim((string) $notes);
        if ($decision === 'reject' && $notes === '') {
            throw ValidationException::withMessages(['review_notes' => 'A reason is required when rejecting.']);
        }

        return DB::transaction(function () use ($report, $actor, $decision, $notes) {
            $previous = $report->status;
            $now = ManilaTime::now();

            if ($decision === 'approve') {
                $report->update([
                    'status' => OvertimeAttachmentReportStatus::Approved,
                    'approved_at' => $now,
                    'finalized_by' => $actor->id,
                    'review_notes' => $notes !== '' ? $notes : null,
                ]);
                $new = OvertimeAttachmentReportStatus::Approved;
            } elseif ($decision === 'return') {
                $report->update([
                    'status' => OvertimeAttachmentReportStatus::Returned,
                    'returned_at' => $now,
                    'review_notes' => $notes,
                ]);
                $new = OvertimeAttachmentReportStatus::Returned;
            } else {
                $report->update([
                    'status' => OvertimeAttachmentReportStatus::Rejected,
                    'rejected_at' => $now,
                    'review_notes' => $notes,
                ]);
                $new = OvertimeAttachmentReportStatus::Rejected;
            }

            $this->recordAction($report, $actor, LeaveApprovalStage::CeoFinalApproval, 'payroll_'.$decision, null, $previous, $new, $notes);
            $this->audit->log($actor, 'overtime_attachment_report_payroll_'.$decision, 'OvertimeAttachmentReport', $report->id, "{$report->report_no} {$decision} by payroll.");

            return $report->fresh(['employee', 'overtimeRequest', 'activities']);
        });
    }

    public function ensurePayrollOvertimeLink(OvertimeAttachmentReport $report): ?OvertimeRequest
    {
        $report->loadMissing(['officialTimeRequest', 'overtimeRequest', 'employee']);

        if ($report->overtime_request_id && $report->overtimeRequest) {
            return $report->overtimeRequest;
        }

        if ($report->officialTimeRequest) {
            $overtime = $this->overtimeSync->upsertFromOfficialTime($report->officialTimeRequest);
            if ($overtime) {
                $report->update(['overtime_request_id' => $overtime->id]);

                return $overtime->fresh();
            }
        }

        $date = $report->date_coverage_start?->toDateString()
            ?? $report->date_coverage_end?->toDateString();

        if (! $date) {
            return null;
        }

        $overtime = OvertimeRequest::query()
            ->where('employee_id', $report->employee_id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($overtime) {
            $report->update(['overtime_request_id' => $overtime->id]);

            return $overtime;
        }

        return null;
    }

    public function alignWithApprovedPayrollOvertime(OvertimeAttachmentReport $report, ?User $actor = null): OvertimeAttachmentReport
    {
        $report = $report->fresh(['overtimeRequest', 'officialTimeRequest']);
        $this->ensurePayrollOvertimeLink($report);
        $report->refresh()->load('overtimeRequest');

        $overtime = $report->overtimeRequest;
        if (! $overtime || $overtime->status !== OvertimeRequestStatus::Approved) {
            return $report;
        }

        if (! $report->pendingPayrollReview()) {
            return $report;
        }

        if ($actor) {
            return $this->decideByPayrollAdmin($report, $actor, 'approve', 'Aligned with approved payroll OT.');
        }

        $now = ManilaTime::now();
        $report->update([
            'status' => OvertimeAttachmentReportStatus::Approved,
            'approved_at' => $now,
            'review_notes' => 'Payroll OT already approved.',
            'current_stage' => null,
        ]);

        return $report->fresh();
    }

    public function reconcilePeriodReports(string $startDate, string $endDate, ?User $actor = null): void
    {
        OvertimeAttachmentReport::query()
            ->where(function ($q) use ($startDate, $endDate) {
                $q->whereBetween('date_coverage_start', [$startDate, $endDate])
                    ->orWhereBetween('date_coverage_end', [$startDate, $endDate])
                    ->orWhereHas('officialTimeRequest', fn ($inner) => $inner->whereBetween('date', [$startDate, $endDate]));
            })
            ->orderBy('id')
            ->each(fn (OvertimeAttachmentReport $report) => $this->alignWithApprovedPayrollOvertime($report, $actor));
    }

    public function decide(
        OvertimeAttachmentReport $report,
        User $actor,
        LeaveDecision $decision,
        string $reason = '',
    ): OvertimeAttachmentReport {
        if ($actor->employee?->id === $report->employee_id) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot endorse or approve your own OT Attachment report.',
            ]);
        }

        if (! $report->status?->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'This OT Attachment report is no longer awaiting action.',
            ]);
        }

        $stage = $report->current_stage;
        if (! $stage) {
            throw ValidationException::withMessages(['decision' => 'This report has no active approval stage.']);
        }

        if ($decision === LeaveDecision::Denied && trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A rejection reason is required.',
            ]);
        }

        $previous = $report->status;

        return DB::transaction(function () use ($report, $actor, $decision, $reason, $stage, $previous) {
            $assignment = $report->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment || ! $assignment->isPending()) {
                throw ValidationException::withMessages([
                    'decision' => 'You are not an authorized pending approver for the current stage.',
                ]);
            }

            $assignment->update([
                'status' => $decision->value,
                'reason' => $reason !== '' ? $reason : null,
                'acted_at' => ManilaTime::now(),
            ]);

            $report->refresh()->load('assignments');
            $outcome = $this->evaluateStage($report, $stage);

            if ($outcome === 'pending') {
                $this->recordAction($report, $actor, $stage, 'decision', $decision, $previous, $report->status, $reason);

                return $report->fresh(['employee.user', 'assignments.user', 'actions.user', 'activities', 'officialTimeRequest']);
            }

            if ($outcome === 'denied') {
                $report->update([
                    'status' => OvertimeAttachmentReportStatus::Rejected,
                    'current_stage' => $stage,
                    'rejected_at' => ManilaTime::now(),
                ]);
                $this->recordAction($report, $actor, $stage, 'decision', $decision, $previous, OvertimeAttachmentReportStatus::Rejected, $reason);
                $this->audit->log($actor, 'overtime_attachment_report_rejected', 'OvertimeAttachmentReport', $report->id, "{$report->report_no} was rejected.");

                return $report->fresh(['employee.user', 'assignments.user', 'actions.user', 'activities', 'officialTimeRequest']);
            }

            $mixed = $outcome === 'approved_mixed';
            $next = $report->nextApprovalStageAfter($stage);

            if ($next) {
                $status = $mixed && $stage->isParallel()
                    ? OvertimeAttachmentReportStatus::Verified
                    : OvertimeAttachmentReportStatus::UnderReview;
                $report->update([
                    'status' => $status,
                    'current_stage' => $next,
                ]);
                $this->recordAction($report, $actor, $stage, 'decision', $decision, $previous, $status, $reason);
            } else {
                $now = ManilaTime::now();
                $report->update([
                    'status' => OvertimeAttachmentReportStatus::Approved,
                    'current_stage' => $stage,
                    'approved_at' => $now,
                    'finalized_by' => $actor->id,
                    'approved_by' => $report->approved_by ?: $actor->employee?->fullName() ?: $actor->name,
                ]);
                $this->recordAction($report, $actor, $stage, 'decision', $decision, $previous, OvertimeAttachmentReportStatus::Approved, $reason);
            }

            $this->audit->log($actor, 'overtime_attachment_report_'.$decision->value, 'OvertimeAttachmentReport', $report->id, "{$actor->name} {$decision->value} {$report->report_no}.");

            return $report->fresh(['employee.user', 'assignments.user', 'actions.user', 'activities', 'officialTimeRequest']);
        });
    }

    public function returnForRevision(OvertimeAttachmentReport $report, User $actor, string $reason): OvertimeAttachmentReport
    {
        if ($actor->employee?->id === $report->employee_id) {
            throw ValidationException::withMessages(['reason' => 'You cannot return your own report.']);
        }

        if (! $report->status?->isOpen() || ! $report->current_stage) {
            throw ValidationException::withMessages(['reason' => 'This report is not awaiting your action.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Revision notes are required when returning a report.']);
        }

        $stage = $report->current_stage;
        $previous = $report->status;

        return DB::transaction(function () use ($report, $actor, $reason, $stage, $previous) {
            $assignment = $report->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $assignment) {
                throw ValidationException::withMessages(['reason' => 'You are not authorized to return this report at the current stage.']);
            }

            $report->update([
                'status' => OvertimeAttachmentReportStatus::Returned,
                'current_stage' => null,
                'returned_at' => ManilaTime::now(),
                'review_notes' => $reason,
            ]);

            $this->recordAction($report, $actor, $stage, 'returned', null, $previous, OvertimeAttachmentReportStatus::Returned, $reason);
            $this->audit->log($actor, 'overtime_attachment_report_returned', 'OvertimeAttachmentReport', $report->id, "{$report->report_no} returned for revision.");

            return $report->fresh(['employee.user', 'assignments.user', 'actions.user', 'activities', 'officialTimeRequest']);
        });
    }

    public function cancel(OvertimeAttachmentReport $report, User $actor, string $reason = ''): OvertimeAttachmentReport
    {
        if (! $report->canBeCancelledByEmployee() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['report' => 'This report can no longer be cancelled.']);
        }

        if ($actor->employee?->id !== $report->employee_id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['report' => 'You can only cancel your own reports.']);
        }

        $previous = $report->status;

        return DB::transaction(function () use ($report, $actor, $reason, $previous) {
            $report->update([
                'status' => OvertimeAttachmentReportStatus::Cancelled,
                'cancelled_at' => ManilaTime::now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason !== '' ? $reason : null,
            ]);

            $this->recordAction(
                $report,
                $actor,
                $report->current_stage ?? LeaveApprovalStage::ImmediateSupervisor,
                'cancelled',
                null,
                $previous,
                OvertimeAttachmentReportStatus::Cancelled,
                $reason
            );
            $this->audit->log($actor, 'overtime_attachment_report_cancelled', 'OvertimeAttachmentReport', $report->id, "{$actor->name} cancelled {$report->report_no}.");

            return $report->fresh(['employee.user', 'assignments.user', 'actions.user', 'activities', 'officialTimeRequest']);
        });
    }

    public function pendingFor(User $user)
    {
        return OvertimeAttachmentReport::query()
            ->whereHas('assignments', function ($assignment) use ($user) {
                $assignment->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('overtime_attachment_approval_assignments.stage', 'overtime_attachment_reports.current_stage');
            })
            ->with(['employee.department', 'officialTimeRequest', 'activities', 'assignments'])
            ->latest('submitted_at')
            ->tap(fn ($query) => DepartmentApprovalScope::constrainToApproverDepartments(
                $query,
                $user,
                ApprovalTransactionType::OvertimeAttachmentReport,
            ));
    }

    public function historyFor(User $user)
    {
        return OvertimeAttachmentReport::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('acted_at'))
            ->with(['employee.department', 'officialTimeRequest', 'activities', 'assignments'])
            ->latest('submitted_at')
            ->tap(fn ($query) => DepartmentApprovalScope::constrainToApproverDepartments(
                $query,
                $user,
                ApprovalTransactionType::OvertimeAttachmentReport,
            ));
    }

    public function userCanAct(User $user, OvertimeAttachmentReport $report): bool
    {
        if ($user->employee?->id === $report->employee_id) {
            return false;
        }

        if (! $report->status?->isOpen() || ! $report->current_stage) {
            return false;
        }

        if (! DepartmentApprovalScope::approverCanAccessRequestDepartment($user, $report->department_id)) {
            return false;
        }

        return $report->assignments()
            ->where('stage', $report->current_stage->value)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function userIsAssignedApprover(User $user): bool
    {
        return OvertimeAttachmentApprovalAssignment::query()->where('user_id', $user->id)->exists();
    }

    /** @return array<string, int> */
    public function dashboardCounts(Employee $employee): array
    {
        $mine = OvertimeAttachmentReport::query()->ownedByEmployee($employee);

        return [
            'total' => (clone $mine)->count(),
            'pending' => (clone $mine)->where('status', OvertimeAttachmentReportStatus::Submitted)->count(),
            'approved' => (clone $mine)->where('status', OvertimeAttachmentReportStatus::Approved)->count(),
            'rejected' => (clone $mine)->where('status', OvertimeAttachmentReportStatus::Rejected)->count(),
            'returned' => (clone $mine)->where('status', OvertimeAttachmentReportStatus::Returned)->count(),
            'cancelled' => (clone $mine)->where('status', OvertimeAttachmentReportStatus::Cancelled)->count(),
        ];
    }

    /** @param  array<int, array<string, mixed>>  $activities */
    private function syncActivities(OvertimeAttachmentReport $report, array $activities, bool $submitting): void
    {
        $normalized = [];
        foreach (array_values($activities) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $description = trim((string) ($row['activity_description'] ?? ''));
            if ($description === '') {
                if ($submitting) {
                    throw ValidationException::withMessages(["activities.{$index}.activity_description" => 'Each activity must have a description.']);
                }

                continue;
            }

            $date = (string) ($row['activity_date'] ?? '');
            $from = $this->normalizeTimeInput($row['time_from'] ?? null);
            $to = $this->normalizeTimeInput($row['time_to'] ?? null);

            if ($submitting) {
                if ($date === '') {
                    throw ValidationException::withMessages(["activities.{$index}.activity_date" => 'Activity date is required.']);
                }
                if (! $from || ! $to) {
                    throw ValidationException::withMessages(["activities.{$index}.time_from" => 'Time from and time to are required.']);
                }
            }

            $duration = ($from && $to) ? $this->durationMinutes($from, $to) : 0;
            if ($submitting && $duration <= 0) {
                throw ValidationException::withMessages(["activities.{$index}.time_to" => 'Activity duration must be greater than zero.']);
            }

            $normalized[] = [
                'sort_order' => $index,
                'activity_description' => $description,
                'activity_date' => $date !== '' ? $date : ManilaTime::todayDate(),
                'time_from' => $from ?? '08:00:00',
                'time_to' => $to ?? '17:00:00',
                'duration_minutes' => $duration,
                'status_remark' => trim((string) ($row['status_remark'] ?? '')) ?: null,
                'reviewed_vouched_by' => trim((string) ($row['reviewed_vouched_by'] ?? '')) ?: null,
            ];
        }

        if ($submitting && $normalized === []) {
            throw ValidationException::withMessages(['activities' => 'Add at least one activity.']);
        }

        $report->activities()->delete();
        foreach ($normalized as $payload) {
            $report->activities()->create($payload);
        }

        $report->update(['total_activity_minutes' => collect($normalized)->sum('duration_minutes')]);
    }

    private function validatedPayload(
        array $data,
        bool $submitting,
        ?OvertimeAttachmentReport $existing,
        OvertimeRequest $overtimeRequest,
    ): array {
        $start = (string) ($data['date_coverage_start'] ?? '');
        $end = (string) ($data['date_coverage_end'] ?? '');
        $preparedBy = trim((string) ($data['prepared_by'] ?? ''));
        $approvedBy = trim((string) ($data['approved_by'] ?? ''));
        $dateSubmitted = $data['date_submitted'] ?? null;

        if ($submitting) {
            if ($start === '' || $end === '') {
                throw ValidationException::withMessages(['date_coverage_start' => 'Date coverage start and end are required.']);
            }
            if (strtotime($end) < strtotime($start)) {
                throw ValidationException::withMessages(['date_coverage_end' => 'Coverage end must be on or after the start date.']);
            }
            if ($preparedBy === '') {
                throw ValidationException::withMessages(['prepared_by' => 'Prepared by name is required.']);
            }
        }

        $otDate = $overtimeRequest->attendance_date?->toDateString() ?? ManilaTime::todayDate();

        return [
            'date_coverage_start' => $start !== '' ? $start : ($existing?->date_coverage_start?->toDateString() ?? $otDate),
            'date_coverage_end' => $end !== '' ? $end : ($existing?->date_coverage_end?->toDateString() ?? $otDate),
            'date_submitted' => $dateSubmitted ?: ($existing?->date_submitted?->toDateString()),
            'prepared_by' => $preparedBy !== '' ? $preparedBy : $existing?->prepared_by,
            'approved_by' => $approvedBy !== '' ? $approvedBy : $existing?->approved_by,
        ];
    }

    private function resolveOvertimeRequest(Employee $employee, array $data, ?OvertimeAttachmentReport $existing): OvertimeRequest
    {
        if ($existing) {
            $overtimeRequest = $existing->overtimeRequest;
        } else {
            $id = (int) ($data['overtime_request_id'] ?? 0);
            $overtimeRequest = OvertimeRequest::query()->find($id);
        }

        if (! $overtimeRequest || $overtimeRequest->employee_id !== $employee->id) {
            throw ValidationException::withMessages(['overtime_request_id' => 'Select a valid payroll overtime (OT) row.']);
        }

        if ($overtimeRequest->recorded_minutes <= 0) {
            throw ValidationException::withMessages(['overtime_request_id' => 'This OT row has no recorded overtime from DTR.']);
        }

        return $overtimeRequest;
    }

    private function assertNoDuplicateReport(OvertimeRequest $overtimeRequest): void
    {
        if ($overtimeRequest->attachmentReport()->exists()) {
            throw ValidationException::withMessages(['overtime_request_id' => 'An attachment report already exists for this OT row.']);
        }
    }

    private function assertEmployeeOwns(OvertimeAttachmentReport $report, Employee $employee): void
    {
        if ($report->employee_id !== $employee->id) {
            throw ValidationException::withMessages(['report' => 'You do not have access to this report.']);
        }
    }

    private function normalizeTimeInput(mixed $raw): ?string
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $value = trim((string) $raw);
        if ($value === '') {
            return null;
        }

        if (strlen($value) === 5) {
            $value .= ':00';
        }

        return $value;
    }

    private function durationMinutes(string $from, string $to): int
    {
        $start = strtotime("1970-01-01 {$from}");
        $end = strtotime("1970-01-01 {$to}");
        if ($end <= $start) {
            $end += 86400;
        }

        return max(0, (int) (($end - $start) / 60));
    }

    /** @return 'pending'|'approved'|'approved_mixed'|'denied' */
    private function evaluateStage(OvertimeAttachmentReport $report, LeaveApprovalStage $stage): string
    {
        $rows = $report->assignments->where('stage', $stage)->values();
        $active = $rows->reject(fn (OvertimeAttachmentApprovalAssignment $row) => $row->status === 'skipped');
        $pending = $active->filter->isPending();
        $approved = $active->filter->isApproved();
        $denied = $active->filter->isDenied();
        $total = $active->count();

        if ($total === 0) {
            return 'approved';
        }

        if ($stage->usesCentralEndorsementRules()) {
            $mode = $report->snapshotEndorsementModeForStage($stage);
            if ($mode === EndorsementMode::Sequential) {
                if ($denied->isNotEmpty()) {
                    return 'denied';
                }
                if ($approved->isNotEmpty()) {
                    return 'approved';
                }

                return 'pending';
            }

            $rule = $report->snapshotParallelRuleForStage($stage);

            return match ($rule) {
                LeaveParallelRule::Any => $this->evalAny($approved, $denied, $pending, $total),
                LeaveParallelRule::Majority => $this->evalMajority($approved, $denied, $pending, $total),
                LeaveParallelRule::All => $this->evalAll($approved, $denied, $pending, $total),
            };
        }

        if ($denied->isNotEmpty()) {
            return 'denied';
        }
        if ($approved->isNotEmpty()) {
            return 'approved';
        }

        return 'pending';
    }

    private function evalAll($approved, $denied, $pending, int $total): string
    {
        if ($denied->isNotEmpty()) {
            return 'denied';
        }
        if ($approved->count() === $total) {
            return 'approved';
        }

        return 'pending';
    }

    private function evalAny($approved, $denied, $pending, int $total): string
    {
        if ($approved->isNotEmpty()) {
            return $denied->isNotEmpty() ? 'approved_mixed' : 'approved';
        }
        if ($pending->isEmpty() && $denied->count() === $total) {
            return 'denied';
        }

        return 'pending';
    }

    private function evalMajority($approved, $denied, $pending, int $total): string
    {
        $needed = (int) floor($total / 2) + 1;

        if ($approved->count() >= $needed) {
            return $denied->isNotEmpty() ? 'approved_mixed' : 'approved';
        }
        if ($denied->count() > ($total - $needed)) {
            return 'denied';
        }

        return 'pending';
    }

    private function recordAction(
        OvertimeAttachmentReport $report,
        User $actor,
        LeaveApprovalStage $stage,
        string $action,
        ?LeaveDecision $decision,
        ?OvertimeAttachmentReportStatus $previous,
        OvertimeAttachmentReportStatus $next,
        ?string $reason = null,
    ): void {
        OvertimeAttachmentApprovalAction::query()->create([
            'overtime_attachment_report_id' => $report->id,
            'assignment_id' => $report->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->value('id'),
            'user_id' => $actor->id,
            'stage' => $stage,
            'action' => $action,
            'decision' => $decision,
            'previous_status' => $previous,
            'new_status' => $next,
            'reason' => $reason !== '' ? $reason : null,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 1000),
            'acted_at' => ManilaTime::now(),
        ]);
    }

    private function nextNumber(): string
    {
        $year = ManilaTime::now()->year;
        $prefix = "OTAR-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $latest = OvertimeAttachmentReport::query()
                ->where('report_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('report_no')
                ->value('report_no');

            $sequence = $latest ? ((int) substr($latest, -6)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
