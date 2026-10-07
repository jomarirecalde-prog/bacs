<?php

namespace App\Services;

use App\Enums\ApprovalTransactionType;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\OfficialTimeStatus;
use App\Models\Employee;
use App\Models\OfficialTimeApprovalAction;
use App\Models\OfficialTimeApprovalAssignment;
use App\Models\OfficialTimeRequest;
use App\Models\OfficialTimeType;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class OfficialTimeService
{
    public function __construct(
        private readonly OfficialTimeNotificationService $notifier,
        private readonly AuditLogger $audit,
        private readonly CentralApprovalWorkflowService $centralApproval,
    ) {}

    public function saveDraft(Employee $employee, User $actor, array $data, ?OfficialTimeRequest $existing = null): OfficialTimeRequest
    {
        $payload = $this->validatedPayload($employee, $data, false, $existing);
        $this->assertNoOverlap($employee, $payload, $existing?->id);

        return DB::transaction(function () use ($employee, $actor, $payload, $existing, $data) {
            if ($existing) {
                $this->assertEmployeeOwns($existing, $employee);
                if (! $existing->canBeEditedByEmployee()) {
                    throw ValidationException::withMessages(['official_time' => 'This request can no longer be edited.']);
                }
                $existing->update($payload);
                $request = $existing;
            } else {
                $request = OfficialTimeRequest::query()->create(array_merge($payload, [
                    'request_no' => $this->nextNumber(),
                    'employee_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'designation_id' => $employee->designation_id,
                    'status' => OfficialTimeStatus::Draft,
                    'submitted_by' => $actor->id,
                ]));
            }

            $this->storeAttachment($request, $actor, $data['attachment'] ?? null);
            $this->audit->log($actor, 'official_time_saved', 'OfficialTimeRequest', $request->id, "Draft {$request->request_no} saved.");

            return $request->fresh(['employee.department', 'designation', 'officialTimeType']);
        });
    }

    public function submit(Employee $employee, User $actor, array $data, ?OfficialTimeRequest $existing = null): OfficialTimeRequest
    {
        $payload = $this->validatedPayload($employee, $data, true, $existing);
        $this->assertNoOverlap($employee, $payload, $existing?->id);

        return DB::transaction(function () use ($employee, $actor, $payload, $existing, $data) {
            $this->centralApproval->assertCanSubmit(ApprovalTransactionType::OfficialTime);
            $centralConfig = $this->centralApproval->configurationFor(ApprovalTransactionType::OfficialTime);

            if ($existing) {
                $this->assertEmployeeOwns($existing, $employee);
                if (! $existing->canBeEditedByEmployee()) {
                    throw ValidationException::withMessages(['official_time' => 'This request can no longer be submitted.']);
                }
                $existing->update(array_merge($payload, [
                    'parallel_rule' => $this->centralApproval->parallelRuleFor($centralConfig),
                    'submitted_at' => ManilaTime::now(),
                    'submitted_by' => $actor->id,
                    'returned_at' => null,
                    'rejected_at' => null,
                    'status' => OfficialTimeStatus::PendingSupervisor,
                    'current_stage' => LeaveApprovalStage::ImmediateSupervisor,
                ]));
                $request = $existing->fresh();
                $request->assignments()->delete();
            } else {
                $request = OfficialTimeRequest::query()->create(array_merge($payload, [
                    'request_no' => $this->nextNumber(),
                    'employee_id' => $employee->id,
                    'department_id' => $employee->department_id,
                    'designation_id' => $employee->designation_id,
                    'parallel_rule' => $this->centralApproval->parallelRuleFor($centralConfig),
                    'submitted_at' => ManilaTime::now(),
                    'submitted_by' => $actor->id,
                    'status' => OfficialTimeStatus::PendingSupervisor,
                    'current_stage' => LeaveApprovalStage::ImmediateSupervisor,
                ]));
            }

            $this->storeAttachment($request, $actor, $data['attachment'] ?? null);
            $this->centralApproval->bootstrapOfficialTime($request, $employee);
            $meta = $this->centralApproval->initialStageMeta($centralConfig);

            if ($meta['stage'] === null) {
                $now = ManilaTime::now();
                $request->update([
                    'status' => OfficialTimeStatus::Approved,
                    'current_stage' => null,
                    'approved_at' => $now,
                    'finalized_by' => $actor->id,
                ]);
                $first = null;
            } else {
                $request->load('assignments');
                $first = $request->firstActiveApprovalStage();
                $request->update([
                    'status' => $first?->pendingStatusForOfficialTime() ?? OfficialTimeStatus::Approved,
                    'current_stage' => $first,
                    'approved_at' => $first ? null : ManilaTime::now(),
                    'finalized_by' => $first ? null : $actor->id,
                ]);
            }

            $this->recordAction(
                $request,
                $actor,
                $first ?? LeaveApprovalStage::CeoFinalApproval,
                'submitted',
                null,
                null,
                $request->status,
                'Official Time request submitted.'
            );
            $this->audit->log($actor, 'official_time_submitted', 'OfficialTimeRequest', $request->id, "{$employee->fullName()} submitted {$request->request_no}.");

            return $request->fresh(['employee.department', 'designation', 'officialTimeType', 'assignments.user', 'actions.user']);
        });
    }

    public function afterSubmit(OfficialTimeRequest $request): void
    {
        $request->loadMissing(['employee.user', 'assignments.user']);
        $this->notifier->submitted($request);

        if ($request->status === OfficialTimeStatus::Approved) {
            $this->notifier->approved($request);
        } elseif ($request->current_stage && $request->current_stage !== LeaveApprovalStage::ImmediateSupervisor) {
            $this->notifier->stageReady($request, $request->current_stage);
        }
    }

    public function decide(OfficialTimeRequest $request, User $actor, LeaveDecision $decision, string $reason = ''): OfficialTimeRequest
    {
        if ($actor->employee?->id === $request->employee_id) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot endorse or approve your own Official Time request.',
            ]);
        }

        if (! $request->status?->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'This Official Time request is no longer awaiting action.',
            ]);
        }

        $stage = $request->current_stage;
        if (! $stage) {
            throw ValidationException::withMessages(['decision' => 'This request has no active approval stage.']);
        }

        if ($decision === LeaveDecision::Denied && trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A rejection reason is required.',
            ]);
        }

        $previous = $request->status;

        return DB::transaction(function () use ($request, $actor, $decision, $reason, $stage, $previous) {
            $assignment = $request->assignments()
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

            $request->refresh()->load('assignments');
            $outcome = $this->evaluateStage($request, $stage);

            if ($outcome === 'pending') {
                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, $request->status, $reason);
                $this->notifier->decisionToRequester(
                    $request,
                    'Official Time endorsement update',
                    "{$actor->name} {$decision->label()} {$request->request_no}. Parallel endorsement is still in progress.",
                    $decision === LeaveDecision::Denied ? 'warning' : 'info'
                );

                return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'officialTimeType']);
            }

            if ($outcome === 'denied') {
                $request->update([
                    'status' => OfficialTimeStatus::Denied,
                    'current_stage' => $stage,
                    'rejected_at' => ManilaTime::now(),
                ]);
                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, OfficialTimeStatus::Denied, $reason);
                $this->audit->log($actor, 'official_time_rejected', 'OfficialTimeRequest', $request->id, "{$request->request_no} was rejected.");
                $this->notifier->decisionToRequester($request, 'Official Time rejected', "Official Time {$request->request_no} was rejected.", 'error');

                return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'officialTimeType']);
            }

            $mixed = $outcome === 'approved_mixed';
            $next = $request->nextApprovalStageAfter($stage);

            if ($next) {
                $status = $mixed && $stage->isParallel()
                    ? OfficialTimeStatus::PartiallyApproved
                    : $next->pendingStatusForOfficialTime();
                $request->update([
                    'status' => $status,
                    'current_stage' => $next,
                ]);
                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, $status, $reason);
                $this->notifier->decisionToRequester(
                    $request,
                    $mixed ? 'Official Time partially endorsed' : 'Official Time endorsement update',
                    "{$stage->timelineLabel(true)} completed for {$request->request_no}. It is now with {$next->timelineLabel(true)}.",
                    $mixed ? 'warning' : 'success'
                );
                $this->notifier->stageReady($request->fresh(['assignments.user', 'employee.user']), $next);
            } else {
                $now = ManilaTime::now();
                $request->update([
                    'status' => OfficialTimeStatus::Approved,
                    'current_stage' => $stage,
                    'approved_at' => $now,
                    'finalized_by' => $actor->id,
                ]);
                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, OfficialTimeStatus::Approved, $reason);
                $this->notifier->approved($request->fresh(['employee.user']));
            }

            $this->audit->log($actor, 'official_time_'.$decision->value, 'OfficialTimeRequest', $request->id, "{$actor->name} {$decision->value} {$request->request_no}.");

            return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'officialTimeType']);
        });
    }

    public function returnForRevision(OfficialTimeRequest $request, User $actor, string $reason): OfficialTimeRequest
    {
        if ($actor->employee?->id === $request->employee_id) {
            throw ValidationException::withMessages(['reason' => 'You cannot return your own request.']);
        }

        if (! $request->status?->isOpen() || ! $request->current_stage) {
            throw ValidationException::withMessages(['reason' => 'This request is not awaiting your action.']);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Revision notes are required when returning a request.']);
        }

        $stage = $request->current_stage;
        $previous = $request->status;

        return DB::transaction(function () use ($request, $actor, $reason, $stage, $previous) {
            $assignment = $request->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->where('status', 'pending')
                ->lockForUpdate()
                ->first();

            if (! $assignment) {
                throw ValidationException::withMessages(['reason' => 'You are not authorized to return this request at the current stage.']);
            }

            $request->update([
                'status' => OfficialTimeStatus::Returned,
                'current_stage' => null,
                'returned_at' => ManilaTime::now(),
            ]);

            $this->recordAction($request, $actor, $stage, 'returned', null, $previous, OfficialTimeStatus::Returned, $reason);
            $this->audit->log($actor, 'official_time_returned', 'OfficialTimeRequest', $request->id, "{$request->request_no} returned for revision.");
            $this->notifier->returned($request->fresh(['employee.user']), $reason);

            return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'officialTimeType']);
        });
    }

    public function cancel(OfficialTimeRequest $request, User $actor, string $reason = ''): OfficialTimeRequest
    {
        if (! $request->canBeCancelledByEmployee() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['official_time' => 'This Official Time request can no longer be cancelled.']);
        }

        if ($actor->employee?->id !== $request->employee_id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['official_time' => 'You can only cancel your own Official Time requests.']);
        }

        $previous = $request->status;

        return DB::transaction(function () use ($request, $actor, $reason, $previous) {
            $request->update([
                'status' => OfficialTimeStatus::Cancelled,
                'cancelled_at' => ManilaTime::now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason !== '' ? $reason : null,
            ]);

            $this->recordAction(
                $request,
                $actor,
                $request->current_stage ?? LeaveApprovalStage::ImmediateSupervisor,
                'cancelled',
                null,
                $previous,
                OfficialTimeStatus::Cancelled,
                $reason
            );
            $this->audit->log($actor, 'official_time_cancelled', 'OfficialTimeRequest', $request->id, "{$actor->name} cancelled {$request->request_no}.");
            $this->notifier->cancelled($request->fresh(['employee.user', 'assignments.user']));

            return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'officialTimeType']);
        });
    }

    public function pendingFor(User $user)
    {
        return OfficialTimeRequest::query()
            ->whereHas('assignments', function ($assignment) use ($user) {
                $assignment->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('official_time_approval_assignments.stage', 'official_time_requests.current_stage');
            })
            ->with(['employee.department', 'designation', 'officialTimeType', 'assignments'])
            ->latest('submitted_at');
    }

    public function historyFor(User $user)
    {
        return OfficialTimeRequest::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('acted_at'))
            ->with(['employee.department', 'designation', 'officialTimeType', 'assignments'])
            ->latest('submitted_at');
    }

    public function userCanAct(User $user, OfficialTimeRequest $request): bool
    {
        if ($user->employee?->id === $request->employee_id) {
            return false;
        }

        if (! $request->status?->isOpen() || ! $request->current_stage) {
            return false;
        }

        return $request->assignments()
            ->where('stage', $request->current_stage->value)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function userIsAssignedApprover(User $user): bool
    {
        return OfficialTimeApprovalAssignment::query()->where('user_id', $user->id)->exists();
    }

    /** @return array<string, int> */
    public function dashboardCounts(Employee $employee): array
    {
        $mine = OfficialTimeRequest::query()->ownedByEmployee($employee);

        return [
            'total' => (clone $mine)->count(),
            'pending' => (clone $mine)->whereIn('status', [
                OfficialTimeStatus::PendingSupervisor,
                OfficialTimeStatus::PendingCeoFinalApproval,
                OfficialTimeStatus::PartiallyApproved,
            ])->count(),
            'approved' => (clone $mine)->where('status', OfficialTimeStatus::Approved)->count(),
            'rejected' => (clone $mine)->where('status', OfficialTimeStatus::Denied)->count(),
            'returned' => (clone $mine)->where('status', OfficialTimeStatus::Returned)->count(),
            'cancelled' => (clone $mine)->where('status', OfficialTimeStatus::Cancelled)->count(),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    private function assertNoOverlap(Employee $employee, array $payload, ?int $ignoreId = null): void
    {
        $date = $payload['date'];
        $segments = $this->timeSegmentsFromPayload($payload);

        if ($segments === []) {
            return;
        }

        $conflict = OfficialTimeRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->whereNotIn('status', [
                OfficialTimeStatus::Draft->value,
                OfficialTimeStatus::Denied->value,
                OfficialTimeStatus::Cancelled->value,
            ])
            ->get()
            ->first(function (OfficialTimeRequest $row) use ($segments) {
                foreach ($segments as [$from, $to]) {
                    foreach ($this->timeSegmentsFromRequest($row) as [$otherFrom, $otherTo]) {
                        if ($this->timesOverlap($from, $to, $otherFrom, $otherTo)) {
                            return true;
                        }
                    }
                }

                return false;
            });

        if ($conflict) {
            throw ValidationException::withMessages([
                'am_time_in' => 'An Official Time request already exists during this period.',
            ]);
        }
    }

    private function timesOverlap(string $aFrom, string $aTo, string $bFrom, string $bTo): bool
    {
        $aStart = strtotime("1970-01-01 {$aFrom}");
        $aEnd = strtotime("1970-01-01 {$aTo}");
        $bStart = strtotime("1970-01-01 {$bFrom}");
        $bEnd = strtotime("1970-01-01 {$bTo}");

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    /** @param  array<string, mixed>  $payload
     * @return list<array{0: string, 1: string}>
     */
    private function timeSegmentsFromPayload(array $payload): array
    {
        return $this->timeSegments(
            $payload['am_time_in'] ?? null,
            $payload['am_time_out'] ?? null,
            $payload['pm_time_in'] ?? null,
            $payload['pm_time_out'] ?? null,
            $payload['time_from'] ?? null,
            $payload['time_to'] ?? null,
        );
    }

    /** @return list<array{0: string, 1: string}> */
    private function timeSegmentsFromRequest(OfficialTimeRequest $request): array
    {
        return $this->timeSegments(
            $request->am_time_in,
            $request->am_time_out,
            $request->pm_time_in,
            $request->pm_time_out,
            $request->time_from,
            $request->time_to,
        );
    }

    /** @return list<array{0: string, 1: string}> */
    private function timeSegments(
        ?string $amIn,
        ?string $amOut,
        ?string $pmIn,
        ?string $pmOut,
        ?string $legacyFrom,
        ?string $legacyTo,
    ): array {
        $segments = [];

        if ($amIn && $amOut) {
            $segments[] = [$amIn, $amOut];
        }

        if ($pmIn && $pmOut) {
            $segments[] = [$pmIn, $pmOut];
        }

        if ($segments === [] && $legacyFrom && $legacyTo) {
            $segments[] = [$legacyFrom, $legacyTo];
        }

        return $segments;
    }

    /** @return array<string, mixed> */
    private function validatedPayload(Employee $employee, array $data, bool $submitting, ?OfficialTimeRequest $existing): array
    {
        $type = OfficialTimeType::query()->where('id', (int) ($data['official_time_type_id'] ?? 0))->where('is_active', true)->first();
        if (! $type) {
            throw ValidationException::withMessages(['official_time_type_id' => 'Select a valid Official Time type.']);
        }

        $date = (string) ($data['date'] ?? '');
        $amTimeIn = $this->normalizeTimeInput($data['am_time_in'] ?? null) ?? $existing?->am_time_in;
        $amTimeOut = $this->normalizeTimeInput($data['am_time_out'] ?? null) ?? $existing?->am_time_out;
        $pmTimeIn = $this->normalizeTimeInput($data['pm_time_in'] ?? null) ?? $existing?->pm_time_in;
        $pmTimeOut = $this->normalizeTimeInput($data['pm_time_out'] ?? null) ?? $existing?->pm_time_out;
        $purpose = trim((string) ($data['purpose'] ?? ''));
        $activity = trim((string) ($data['activity'] ?? ''));
        $location = trim((string) ($data['location'] ?? ''));
        $remarks = trim((string) ($data['remarks'] ?? ''));

        if ($submitting) {
            if ($date === '') {
                throw ValidationException::withMessages(['date' => 'Official Time date is required.']);
            }
            if (! $amTimeIn || ! $amTimeOut || ! $pmTimeIn || ! $pmTimeOut) {
                throw ValidationException::withMessages(['am_time_in' => 'AM and PM time entries are required.']);
            }
            if ($type->requires_attachment && ! ($data['attachment'] ?? null) && ! $existing?->attachment_path) {
                throw ValidationException::withMessages(['attachment' => 'Supporting document is required for this Official Time type.']);
            }
        }

        $this->validatePunchOrder($amTimeIn, $amTimeOut, $pmTimeIn, $pmTimeOut);

        $duration = $this->durationMinutesFromPunches($amTimeIn, $amTimeOut, $pmTimeIn, $pmTimeOut);

        if ($submitting && $duration <= 0) {
            throw ValidationException::withMessages(['pm_time_out' => 'Total duration must be greater than zero.']);
        }

        $timeFrom = $amTimeIn ?? $existing?->time_from ?? '08:00:00';
        $timeTo = $pmTimeOut ?? $amTimeOut ?? $existing?->time_to ?? '17:00:00';

        return [
            'official_time_type_id' => $type->id,
            'date' => $date !== '' ? $date : ($existing?->date?->toDateString() ?? ManilaTime::todayDate()),
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'am_time_in' => $amTimeIn,
            'am_time_out' => $amTimeOut,
            'pm_time_in' => $pmTimeIn,
            'pm_time_out' => $pmTimeOut,
            'duration_minutes' => $duration > 0 ? $duration : ($existing?->duration_minutes ?? 0),
            'purpose' => $purpose !== '' ? $purpose : ($existing?->purpose ?? $type->name),
            'activity' => $activity !== '' ? $activity : null,
            'location' => $location !== '' ? $location : null,
            'remarks' => $remarks !== '' ? $remarks : null,
        ];
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

    private function validatePunchOrder(?string $amIn, ?string $amOut, ?string $pmIn, ?string $pmOut): void
    {
        if ($amIn && $amOut && strtotime("1970-01-01 {$amOut}") <= strtotime("1970-01-01 {$amIn}")) {
            throw ValidationException::withMessages(['am_time_out' => 'AM Time Out must be later than AM Time In.']);
        }

        if ($pmIn && $pmOut && strtotime("1970-01-01 {$pmOut}") <= strtotime("1970-01-01 {$pmIn}")) {
            throw ValidationException::withMessages(['pm_time_out' => 'PM Time Out must be later than PM Time In.']);
        }

        if ($amOut && $pmIn && strtotime("1970-01-01 {$pmIn}") <= strtotime("1970-01-01 {$amOut}")) {
            throw ValidationException::withMessages(['pm_time_in' => 'PM Time In must be later than AM Time Out.']);
        }
    }

    private function durationMinutesFromPunches(?string $amIn, ?string $amOut, ?string $pmIn, ?string $pmOut): int
    {
        $duration = 0;

        if ($amIn && $amOut) {
            $duration += (int) ((strtotime("1970-01-01 {$amOut}") - strtotime("1970-01-01 {$amIn}")) / 60);
        }

        if ($pmIn && $pmOut) {
            $duration += (int) ((strtotime("1970-01-01 {$pmOut}") - strtotime("1970-01-01 {$pmIn}")) / 60);
        }

        return max(0, $duration);
    }

    private function assertEmployeeOwns(OfficialTimeRequest $request, Employee $employee): void
    {
        if ($request->employee_id !== $employee->id) {
            throw ValidationException::withMessages(['official_time' => 'You do not have access to this Official Time request.']);
        }
    }

    private function storeAttachment(OfficialTimeRequest $request, User $actor, mixed $file): void
    {
        if (! $file instanceof UploadedFile || ! $file->isValid()) {
            return;
        }

        $mime = $file->getMimeType();
        $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
        if (! in_array($mime, $allowed, true)) {
            throw ValidationException::withMessages(['attachment' => 'Attachment must be PDF or image files.']);
        }

        if ($file->getSize() > 5 * 1024 * 1024) {
            throw ValidationException::withMessages(['attachment' => 'Attachment must be 5 MB or smaller.']);
        }

        if ($request->attachment_path) {
            Storage::disk('local')->delete($request->attachment_path);
        }

        $path = $file->store('official-time-attachments/'.$request->id, 'local');
        $request->update([
            'attachment_path' => $path,
            'attachment_name' => $file->getClientOriginalName(),
        ]);
    }

    /** @return 'pending'|'approved'|'approved_mixed'|'denied' */
    private function evaluateStage(OfficialTimeRequest $request, LeaveApprovalStage $stage): string
    {
        $rows = $request->assignments->where('stage', $stage)->values();
        $active = $rows->reject(fn (OfficialTimeApprovalAssignment $row) => $row->status === 'skipped');
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

        $rule = $request->parallel_rule ?? LeaveParallelRule::All;

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

    private function recordAction(
        OfficialTimeRequest $request,
        User $actor,
        LeaveApprovalStage $stage,
        string $action,
        ?LeaveDecision $decision,
        ?OfficialTimeStatus $previous,
        OfficialTimeStatus $next,
        ?string $reason = null,
    ): void {
        OfficialTimeApprovalAction::query()->create([
            'official_time_request_id' => $request->id,
            'assignment_id' => $request->assignments()
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
        $prefix = "OT-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $latest = OfficialTimeRequest::query()
                ->where('request_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('request_no')
                ->value('request_no');

            $sequence = $latest ? ((int) substr($latest, -6)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }
}
