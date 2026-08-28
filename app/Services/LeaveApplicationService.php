<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\LeavePaymentType;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Enums\SpecialLeaveType;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalAction;
use App\Models\LeaveApprovalAssignment;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveAttendanceConflict;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveApplicationService
{
    public function __construct(
        private readonly LeaveDayCalculator $days,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveNotificationService $notifier,
        private readonly AuditLogger $audit,
        private readonly LeaveResolver $leaveResolver,
    ) {}

    public function submit(Employee $employee, User $actor, array $data): LeaveApplication
    {
        $this->assertEmployeeCanApply($employee, $actor);
        $this->days->assertValidRange($data['start_date'], $data['end_date']);

        $type = LeaveType::from($data['leave_type']);
        $special = filled($data['special_leave_type'] ?? null)
            ? SpecialLeaveType::from($data['special_leave_type'])
            : null;

        if ($type === LeaveType::Special && ! $special) {
            throw ValidationException::withMessages([
                'special_leave_type' => 'Select the applicable Special Leave type.',
            ]);
        }

        if ($type !== LeaveType::Special) {
            $special = null;
        }

        $requested = $this->days->days($employee, $data['start_date'], $data['end_date'], $type, $special);

        if ($requested <= 0) {
            throw ValidationException::withMessages([
                'start_date' => 'The selected dates do not include any chargeable leave days.',
            ]);
        }

        if ($type === LeaveType::Birthday && $requested > 1) {
            throw ValidationException::withMessages([
                'end_date' => 'Birthday Leave is limited to 1 day.',
            ]);
        }

        $overlap = LeaveApplication::query()
            ->overlapping($employee->id, $data['start_date'], $data['end_date'])
            ->first();

        if ($overlap) {
            throw ValidationException::withMessages([
                'start_date' => "These dates overlap {$overlap->application_number} ({$overlap->status->label()}).",
            ]);
        }

        $legacy = Leave::query()
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('start_date', '<=', $data['end_date'])
            ->where('end_date', '>=', $data['start_date'])
            ->first();

        if ($legacy) {
            throw ValidationException::withMessages([
                'start_date' => 'These dates overlap an already approved leave on the DTR.',
            ]);
        }

        $signature = $this->storeSignature($data['employee_signature'] ?? null, $employee);

        return DB::transaction(function () use ($employee, $actor, $data, $type, $special, $requested, $signature) {
            $workflow = LeaveApprovalWorkflow::forDepartment($employee->department_id);
            $application = LeaveApplication::query()->create([
                'application_number' => $this->nextNumber(),
                'employee_id' => $employee->id,
                'department_id' => $employee->department_id,
                'leave_type' => $type,
                'special_leave_type' => $special,
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'requested_days' => $requested,
                'reason' => $data['reason'],
                'employee_print_name' => $employee->fullName(),
                'employee_signature' => $signature,
                'declaration_accepted' => true,
                'date_filed' => ManilaTime::now(),
                'status' => LeaveStatus::PendingSupervisor,
                'current_stage' => LeaveApprovalStage::ImmediateSupervisor,
                'parallel_rule' => $workflow->parallel_rule,
                'submitted_by' => $actor->id,
            ]);

            $this->snapshotApprovers($application, $workflow, $employee);
            $first = $this->advanceToNextPendingStage($application, null);
            $application->update([
                'status' => $first?->pendingStatus() ?? LeaveStatus::PendingHr,
                'current_stage' => $first ?? LeaveApprovalStage::HrOfficer,
            ]);

            $this->recordAction($application, $actor, $first ?? LeaveApprovalStage::HrOfficer, 'submitted', null, null, $application->status, 'Leave application submitted.');
            $this->audit->log($actor, 'leave_submitted', 'Leave', $application->id, "{$employee->fullName()} submitted {$application->application_number}.");

            return $application->fresh(['employee.department', 'employee.user', 'assignments.user', 'actions.user']);
        });
    }

    public function afterSubmit(LeaveApplication $application): void
    {
        $this->notifier->submitted($application->loadMissing(['employee.user', 'assignments.user']));

        if ($application->current_stage && $application->current_stage !== LeaveApprovalStage::ImmediateSupervisor) {
            $this->notifier->stageReady($application, $application->current_stage);
        }
    }

    public function decide(LeaveApplication $application, User $actor, LeaveDecision $decision, string $reason = '', ?string $signature = null): LeaveApplication
    {
        if ($actor->employee?->id === $application->employee_id) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot approve or deny your own leave application.',
            ]);
        }

        if (! $application->status?->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'This leave application is no longer awaiting action.',
            ]);
        }

        $stage = $application->current_stage;
        if (! $stage) {
            throw ValidationException::withMessages(['decision' => 'This application has no active approval stage.']);
        }

        if ($stage === LeaveApprovalStage::HrOfficer) {
            throw ValidationException::withMessages([
                'decision' => 'HR processing is completed from the HR section, not the approval action.',
            ]);
        }

        if ($decision === LeaveDecision::Denied && trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when denying a leave application.',
            ]);
        }

        $previous = $application->status;

        return DB::transaction(function () use ($application, $actor, $decision, $reason, $signature, $stage, $previous) {
            $assignment = $application->assignments()
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
                'signature' => $this->storeSignature($signature, $application->employee, $assignment->id),
                'acted_at' => ManilaTime::now(),
            ]);

            $application->refresh()->load('assignments');
            $outcome = $this->evaluateStage($application, $stage);

            if ($outcome === 'pending') {
                $this->recordAction($application, $actor, $stage, 'decision', $decision, $previous, $application->status, $reason);
                $this->audit->log($actor, 'leave_'.$decision->value, 'Leave', $application->id, "{$actor->name} {$decision->value} {$application->application_number} at {$stage->shortLabel()}.");
                $this->notifier->decisionToEmployee(
                    $application,
                    'Leave approval update',
                    "{$actor->name} {$decision->label()} your application {$application->application_number}. Parallel supervisor approval is still in progress.",
                    $decision === LeaveDecision::Denied ? 'warning' : 'info'
                );

                return $application->fresh(['employee.user', 'assignments.user', 'actions.user']);
            }

            if ($outcome === 'denied') {
                $application->update([
                    'status' => LeaveStatus::Denied,
                    'current_stage' => $stage,
                ]);
                $this->recordAction($application, $actor, $stage, 'decision', $decision, $previous, LeaveStatus::Denied, $reason);
                $this->audit->log($actor, 'leave_denied', 'Leave', $application->id, "{$application->application_number} was denied at {$stage->shortLabel()}.");
                $this->notifier->decisionToEmployee(
                    $application,
                    'Leave application denied',
                    "Your leave application {$application->application_number} was denied.",
                    'error'
                );

                return $application->fresh(['employee.user', 'assignments.user', 'actions.user']);
            }

            $mixed = $outcome === 'approved_mixed';
            $next = $this->advanceToNextPendingStage($application, $stage);

            if ($next) {
                $status = $next->pendingStatus();
                $application->update([
                    'status' => $status,
                    'current_stage' => $next,
                ]);
                $this->recordAction($application, $actor, $stage, 'decision', $decision, $previous, $status, $reason);
                $this->notifier->decisionToEmployee(
                    $application,
                    $mixed ? 'Leave partially approved' : 'Leave approval update',
                    "{$stage->shortLabel()} completed for {$application->application_number}. It is now with {$next->shortLabel()}.",
                    $mixed ? 'warning' : 'success'
                );
                $this->notifier->stageReady($application->fresh(['assignments.user', 'employee.user']), $next);
            } else {
                $application->update([
                    'status' => LeaveStatus::PendingHr,
                    'current_stage' => LeaveApprovalStage::HrOfficer,
                ]);
                $this->recordAction($application, $actor, $stage, 'decision', $decision, $previous, LeaveStatus::PendingHr, $reason);
                $this->notifier->decisionToEmployee(
                    $application,
                    'Leave forwarded to HR',
                    "Approvals for {$application->application_number} are complete. HR is processing your leave.",
                    'success'
                );
                $this->notifier->stageReady($application->fresh(['assignments.user', 'employee.user']), LeaveApprovalStage::HrOfficer);
            }

            $this->audit->log($actor, 'leave_'.$decision->value, 'Leave', $application->id, "{$actor->name} {$decision->value} {$application->application_number}.");

            return $application->fresh(['employee.user', 'assignments.user', 'actions.user']);
        });
    }

    public function processHr(LeaveApplication $application, User $actor, array $data): LeaveApplication
    {
        if ($application->status !== LeaveStatus::PendingHr && $application->status !== LeaveStatus::PartiallyApproved) {
            throw ValidationException::withMessages([
                'payment_type' => 'This application is not awaiting HR processing.',
            ]);
        }

        if (! $this->userCanProcessHr($actor, $application)) {
            throw ValidationException::withMessages([
                'payment_type' => 'You are not authorized to process this leave for HR.',
            ]);
        }

        $payment = LeavePaymentType::from($data['payment_type']);
        $decision = LeaveDecision::from($data['decision'] ?? LeaveDecision::Approved->value);

        if ($decision === LeaveDecision::Denied && trim((string) ($data['reason'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when denying a leave application.',
            ]);
        }

        $previous = $application->status;
        $year = (int) $application->start_date->year;

        return DB::transaction(function () use ($application, $actor, $data, $payment, $decision, $previous, $year) {
            $assignment = $application->assignments()
                ->where('stage', LeaveApprovalStage::HrOfficer->value)
                ->where('user_id', $actor->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $assignment && ! $actor->isAdmin()) {
                throw ValidationException::withMessages([
                    'payment_type' => 'You are not authorized to process this leave for HR.',
                ]);
            }
            $code = $application->balanceCode();
            $employee = $application->employee()->lockForUpdate()->first();
            $vacation = $this->balances->forEmployee($employee, LeaveType::Vacation->value, $year);
            $applied = $this->balances->forEmployee($employee, $code, $year);

            if ($decision === LeaveDecision::Approved && $payment === LeavePaymentType::WithPay) {
                $this->balances->deductForApplication($application, $actor, (float) $application->requested_days, $year);
                $applied = $this->balances->forEmployee($employee, $code, $year)->fresh();
            }

            $leave = null;
            $conflict = false;

            if ($decision === LeaveDecision::Approved) {
                $leave = Leave::query()->create([
                    'employee_id' => $employee->id,
                    'start_date' => $application->start_date,
                    'end_date' => $application->end_date,
                    'type' => $application->leaveTypeLabel(),
                    'status' => 'approved',
                    'remarks' => $application->application_number,
                    'created_by' => $actor->id,
                ]);
                $this->leaveResolver->flush();
                $conflict = $this->applyToAttendance($application, $employee, $leave);
            }

            $now = ManilaTime::now();
            $signature = $this->storeSignature($data['signature'] ?? null, $employee, 'hr-'.$application->id);

            if ($assignment) {
                $assignment->update([
                    'status' => $decision->value,
                    'reason' => $data['reason'] ?? $data['hr_remarks'] ?? null,
                    'signature' => $signature,
                    'acted_at' => $now,
                ]);
            } else {
                LeaveApprovalAssignment::query()->create([
                    'leave_application_id' => $application->id,
                    'stage' => LeaveApprovalStage::HrOfficer,
                    'user_id' => $actor->id,
                    'employee_id' => $actor->employee?->id,
                    'approver_name' => $actor->name,
                    'approver_position' => $actor->employee?->position ?? $actor->role?->label(),
                    'approver_role' => $actor->role?->value,
                    'status' => $decision->value,
                    'reason' => $data['reason'] ?? $data['hr_remarks'] ?? null,
                    'signature' => $signature,
                    'acted_at' => $now,
                ]);
            }

            $application->assignments()
                ->where('stage', LeaveApprovalStage::HrOfficer->value)
                ->where('status', 'pending')
                ->update(['status' => 'skipped', 'acted_at' => $now, 'reason' => 'Completed by another HR officer.']);

            $status = $decision === LeaveDecision::Approved ? LeaveStatus::Approved : LeaveStatus::Denied;

            $application->update([
                'status' => $status,
                'payment_type' => $payment,
                'hr_sil_as_of' => $data['hr_sil_as_of'] ?? $now->toDateString(),
                'hr_sil_balance' => $vacation->fresh()->remaining(),
                'hr_leave_taken' => (float) $applied->used_days,
                'hr_leave_balance' => $applied->remaining(),
                'hr_remarks' => $data['hr_remarks'] ?? $data['reason'] ?? null,
                'attendance_conflict' => $conflict,
                'finalized_at' => $now,
                'finalized_by' => $actor->id,
                'leave_id' => $leave?->id,
            ]);

            $this->recordAction(
                $application,
                $actor,
                LeaveApprovalStage::HrOfficer,
                'hr_processed',
                $decision,
                $previous,
                $status,
                $data['hr_remarks'] ?? $data['reason'] ?? null,
                $signature
            );

            $this->audit->log($actor, 'leave_hr_processed', 'Leave', $application->id, "HR processed {$application->application_number} as {$status->label()} ({$payment->label()}).");

            if ($status === LeaveStatus::Approved) {
                $this->notifier->decisionToEmployee(
                    $application,
                    'Leave application approved',
                    "Your leave application {$application->application_number} has been fully approved.",
                    'success'
                );
            } else {
                $this->notifier->decisionToEmployee(
                    $application,
                    'Leave application denied',
                    "HR denied leave application {$application->application_number}.",
                    'error'
                );
            }

            if ($conflict) {
                $this->notifier->needsEmployeeAction(
                    $application,
                    'HR flagged a time-entry conflict on one or more approved leave dates. Existing DTR punches were preserved.'
                );
            }

            return $application->fresh(['employee.department', 'employee.user', 'assignments.user', 'actions.user', 'conflicts']);
        });
    }

    public function cancel(LeaveApplication $application, User $actor, string $reason = ''): LeaveApplication
    {
        if (! $application->canBeCancelled()) {
            throw ValidationException::withMessages([
                'application' => 'This leave application can no longer be cancelled.',
            ]);
        }

        $previous = $application->status;

        return DB::transaction(function () use ($application, $actor, $reason, $previous) {
            $application->update([
                'status' => LeaveStatus::Cancelled,
                'cancelled_at' => ManilaTime::now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason !== '' ? $reason : null,
            ]);

            $this->recordAction(
                $application,
                $actor,
                $application->current_stage ?? LeaveApprovalStage::ImmediateSupervisor,
                'cancelled',
                null,
                $previous,
                LeaveStatus::Cancelled,
                $reason
            );
            $this->audit->log($actor, 'leave_cancelled', 'Leave', $application->id, "{$actor->name} cancelled {$application->application_number}.");
            $this->notifier->cancelled($application->fresh(['employee.user', 'assignments.user']));

            return $application->fresh(['employee.user', 'assignments.user', 'actions.user']);
        });
    }

    public function pendingFor(User $user)
    {
        return LeaveApplication::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'pending'))
            ->where(function ($q) use ($user) {
                $q->whereHas('assignments', function ($assignment) use ($user) {
                    $assignment->where('user_id', $user->id)
                        ->where('status', 'pending')
                        ->whereColumn('leave_approval_assignments.stage', 'leave_applications.current_stage');
                });
            })
            ->with(['employee.department', 'assignments'])
            ->latest('date_filed');
    }

    public function historyFor(User $user)
    {
        return LeaveApplication::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('acted_at'))
            ->with(['employee.department', 'assignments'])
            ->latest('date_filed');
    }

    public function userCanAct(User $user, LeaveApplication $application): bool
    {
        if ($user->employee?->id === $application->employee_id) {
            return false;
        }

        if (! $application->status?->isOpen() || ! $application->current_stage) {
            return false;
        }

        return $application->assignments()
            ->where('stage', $application->current_stage->value)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function userCanProcessHr(User $user, LeaveApplication $application): bool
    {
        if ($application->status !== LeaveStatus::PendingHr && $application->status !== LeaveStatus::PartiallyApproved) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        return $application->assignments()
            ->where('stage', LeaveApprovalStage::HrOfficer->value)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function userIsAssignedApprover(User $user): bool
    {
        return LeaveApprovalAssignment::query()->where('user_id', $user->id)->exists()
            || \App\Models\LeaveApprovalWorkflowApprover::query()->where('user_id', $user->id)->exists();
    }

    private function snapshotApprovers(LeaveApplication $application, LeaveApprovalWorkflow $workflow, Employee $employee): void
    {
        $workflow->load(['approvers.user.employee']);

        foreach (LeaveApprovalStage::sequence() as $stage) {
            $approvers = $workflow->approvers->where('stage', $stage);
            $sort = 0;

            foreach ($approvers as $row) {
                $user = $row->user;
                if (! $user || $user->employee?->id === $employee->id) {
                    continue;
                }

                $this->createAssignment($application, $stage, $user, $sort++);
            }
        }

        if ($application->assignments()->where('stage', LeaveApprovalStage::HrOfficer->value)->doesntExist()) {
            User::query()
                ->where('role', UserRole::Admin)
                ->where('status', 'active')
                ->get()
                ->each(function (User $user, int $index) use ($application, $employee) {
                    if ($user->employee?->id === $employee->id) {
                        return;
                    }
                    $this->createAssignment($application, LeaveApprovalStage::HrOfficer, $user, $index);
                });
        }
    }

    private function createAssignment(LeaveApplication $application, LeaveApprovalStage $stage, User $user, int $sort): void
    {
        LeaveApprovalAssignment::query()->create([
            'leave_application_id' => $application->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'approver_role' => $user->role?->value,
            'status' => 'pending',
            'sort_order' => $sort,
        ]);
    }

    private function advanceToNextPendingStage(LeaveApplication $application, ?LeaveApprovalStage $from): ?LeaveApprovalStage
    {
        $stages = LeaveApprovalStage::sequence();
        $started = $from === null;

        foreach ($stages as $stage) {
            if (! $started) {
                if ($stage === $from) {
                    $started = true;
                }
                continue;
            }

            $count = $application->assignments()->where('stage', $stage->value)->count();
            if ($count > 0) {
                return $stage;
            }
        }

        return null;
    }

    /**
     * @return 'pending'|'approved'|'approved_mixed'|'denied'
     */
    private function evaluateStage(LeaveApplication $application, LeaveApprovalStage $stage): string
    {
        $rows = $application->assignments->where('stage', $stage)->values();
        $active = $rows->reject(fn (LeaveApprovalAssignment $row) => $row->status === 'skipped');
        $pending = $active->filter->isPending();
        $approved = $active->filter->isApproved();
        $denied = $active->filter->isDenied();
        $total = $active->count();

        if ($total === 0) {
            return 'approved';
        }

        if (! $stage->isParallel()) {
            if ($denied->isNotEmpty()) {
                return 'denied';
            }
            if ($approved->isNotEmpty()) {
                return 'approved';
            }

            return 'pending';
        }

        $rule = $application->parallel_rule ?? LeaveParallelRule::All;

        return match ($rule) {
            LeaveParallelRule::Any => $this->evalAny($approved, $denied, $pending, $total),
            LeaveParallelRule::Majority => $this->evalMajority($approved, $denied, $pending, $total),
            LeaveParallelRule::All => $this->evalAll($approved, $denied, $pending, $total),
        };
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

    private function applyToAttendance(LeaveApplication $application, Employee $employee, Leave $leave): bool
    {
        $conflict = false;
        $dates = $this->days->dates($application->start_date->toDateString(), $application->end_date->toDateString());

        foreach ($dates as $date) {
            $row = Attendance::query()
                ->where('employee_id', $employee->id)
                ->onDate($date)
                ->lockForUpdate()
                ->first();

            if ($row && ($row->time_in || $row->time_out)) {
                $conflict = true;
                LeaveAttendanceConflict::query()->create([
                    'leave_application_id' => $application->id,
                    'attendance_id' => $row->id,
                    'attendance_date' => $date,
                    'time_in' => $row->time_in,
                    'time_out' => $row->time_out,
                    'attendance_status' => $row->status?->value,
                ]);
                continue;
            }

            $payload = [
                'employee_id' => $employee->id,
                'attendance_date' => $date,
                'status' => AttendanceStatus::OnLeave,
                'remarks' => 'Approved leave '.$application->application_number,
                'total_minutes' => 0,
                'late_minutes' => 0,
                'undertime_minutes' => 0,
                'overtime_minutes' => 0,
            ];

            if ($row) {
                $row->update($payload);
            } else {
                Attendance::query()->create($payload);
            }
        }

        return $conflict;
    }

    private function recordAction(
        LeaveApplication $application,
        User $actor,
        LeaveApprovalStage $stage,
        string $action,
        ?LeaveDecision $decision,
        ?LeaveStatus $previous,
        LeaveStatus $next,
        ?string $reason = null,
        ?string $signature = null
    ): void {
        LeaveApprovalAction::query()->create([
            'leave_application_id' => $application->id,
            'assignment_id' => $application->assignments()
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
            'signature' => $signature,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 1000),
            'acted_at' => ManilaTime::now(),
        ]);
    }

    private function nextNumber(): string
    {
        $year = ManilaTime::now()->year;
        $prefix = "LF-{$year}-";
        $latest = LeaveApplication::query()
            ->where('application_number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('application_number')
            ->value('application_number');

        $sequence = $latest ? ((int) substr($latest, -4)) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function storeSignature(?string $payload, Employee $employee, string|int|null $suffix = null): ?string
    {
        if (! is_string($payload) || ! str_starts_with($payload, 'data:image/')) {
            return $payload ?: null;
        }

        if (! preg_match('#^data:image/(png|jpeg|jpg);base64,(.+)$#', $payload, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) > 400_000) {
            throw ValidationException::withMessages([
                'employee_signature' => 'The signature image is invalid or too large.',
            ]);
        }

        $name = 'leave-signatures/'.$employee->id.'-'.($suffix ?: uniqid('', true)).'.png';
        \Illuminate\Support\Facades\Storage::disk('public')->put($name, $binary);

        return $name;
    }

    private function assertEmployeeCanApply(Employee $employee, User $actor): void
    {
        if ($actor->isManagement() && $actor->employee?->id !== $employee->id) {
            throw ValidationException::withMessages([
                'employee' => 'Leave applications must be submitted from the employee account.',
            ]);
        }

        if ($actor->employee?->id !== $employee->id) {
            throw ValidationException::withMessages([
                'employee' => 'You can only submit leave for your own employee profile.',
            ]);
        }
    }
}
