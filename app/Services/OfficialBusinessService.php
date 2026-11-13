<?php

namespace App\Services;

use App\Enums\ApprovalTransactionType;
use App\Enums\EndorsementMode;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\OfficialBusinessStatus;
use App\Enums\TravelTransportation;
use App\Models\Employee;
use App\Models\LeaveApprovalWorkflow;
use App\Models\OfficialBusinessApprovalAction;
use App\Models\OfficialBusinessApprovalAssignment;
use App\Models\OfficialBusinessRequest;
use App\Models\User;
use App\Support\DepartmentApprovalScope;
use App\Support\ManilaTime;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OfficialBusinessService
{
    public function __construct(
        private readonly OfficialBusinessNotificationService $notifier,
        private readonly EmailNotificationService $emailNotifications,
        private readonly AuditLogger $audit,
        private readonly CentralApprovalWorkflowService $centralApproval,
        private readonly EndorsementWorkflowCoordinator $endorsement,
        private readonly OfficialBusinessAttendanceService $attendanceSync,
    ) {}

    /** @return Collection<int, array{id:int,name:string,position:?string,department:?string,employee_number:?string}> */
    public function searchEmployees(string $term, int $limit = 20): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Employee::query()
            ->active()
            ->with('department:id,name')
            ->where(function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where('full_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like)
                    ->orWhere('position', 'like', $like)
                    ->orWhereHas('department', fn ($dept) => $dept->where('name', 'like', $like));
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->fullName(),
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'employee_number' => $employee->employee_number,
            ]);
    }

    public function saveDraft(Employee $requester, User $actor, array $data, ?OfficialBusinessRequest $existing = null): OfficialBusinessRequest
    {
        $this->assertCanCreate($requester, $actor);
        $payload = $this->validatedPayload($data, false);
        $participants = $this->resolveParticipants($requester, $data, false);

        return DB::transaction(function () use ($requester, $actor, $payload, $participants, $existing) {
            if ($existing) {
                $this->assertRequesterOwnsDraft($existing, $requester);
                $existing->update($payload);
                $request = $existing;
            } else {
                $request = OfficialBusinessRequest::query()->create(array_merge($payload, [
                    'reference_number' => $this->nextNumber(),
                    'employee_id' => $requester->id,
                    'department_id' => $requester->department_id,
                    'status' => OfficialBusinessStatus::Draft,
                    'date_filed' => ManilaTime::todayDate(),
                    'submitted_by' => $actor->id,
                ]));
            }

            $this->syncParticipants($request, $participants['employee_ids'], $participants['external_names']);

            return $request->fresh([
                'employee.department',
                'participants.employee.department',
            ]);
        });
    }

    public function submit(Employee $requester, User $actor, array $data, ?OfficialBusinessRequest $existing = null): OfficialBusinessRequest
    {
        $this->assertCanCreate($requester, $actor);
        $payload = $this->validatedPayload($data, true);
        $participants = $this->resolveParticipants($requester, $data, true);

        if ($participants['employee_ids'] === [] && $participants['external_names'] === []) {
            throw ValidationException::withMessages([
                'participants' => 'Add at least one participant for this official business request.',
            ]);
        }

        return DB::transaction(function () use ($requester, $actor, $payload, $participants, $existing) {
            $this->centralApproval->assertCanSubmit(ApprovalTransactionType::OfficialBusiness, $requester->department_id);
            $centralConfig = $this->centralApproval->configurationFor(ApprovalTransactionType::OfficialBusiness);
            $workflow = LeaveApprovalWorkflow::forDepartment($requester->department_id);

            if ($existing) {
                $this->assertRequesterOwnsDraft($existing, $requester);
                $existing->update(array_merge($payload, [
                    'workflow_id' => $workflow->id,
                    'workflow_version' => $workflow->version,
                    'parallel_rule' => $this->centralApproval->parallelRuleFor($centralConfig, $requester->department_id),
                    'submitted_at' => ManilaTime::now(),
                    'submitted_by' => $actor->id,
                    'status' => OfficialBusinessStatus::PendingSupervisor,
                    'current_stage' => LeaveApprovalStage::ImmediateSupervisor,
                ]));
                $request = $existing->fresh();
                $request->assignments()->delete();
            } else {
                $request = OfficialBusinessRequest::query()->create(array_merge($payload, [
                    'reference_number' => $this->nextNumber(),
                    'employee_id' => $requester->id,
                    'department_id' => $requester->department_id,
                    'workflow_id' => $workflow->id,
                    'workflow_version' => $workflow->version,
                    'parallel_rule' => $this->centralApproval->parallelRuleFor($centralConfig, $requester->department_id),
                    'date_filed' => ManilaTime::todayDate(),
                    'submitted_at' => ManilaTime::now(),
                    'submitted_by' => $actor->id,
                    'status' => OfficialBusinessStatus::PendingSupervisor,
                    'current_stage' => LeaveApprovalStage::ImmediateSupervisor,
                ]));
            }

            $this->syncParticipants($request, $participants['employee_ids'], $participants['external_names']);

            $this->centralApproval->bootstrapOfficialBusiness($request, $requester);
            $request->load('assignments');

            $first = null;

            if ($this->centralApproval->missingActionableApprovers($request)) {
                $request->update([
                    'status' => OfficialBusinessStatus::PendingApproverAssignment,
                    'current_stage' => null,
                ]);
                $this->centralApproval->notifyPendingApproverAssignment(
                    ApprovalTransactionType::TravelOrder,
                    $requester,
                    $request->reference_number,
                );
            } else {
                $meta = $this->centralApproval->initialStageMeta($centralConfig, $requester->department_id);

                if ($meta['stage'] === null) {
                    $now = ManilaTime::now();
                    $request->update([
                        'status' => OfficialBusinessStatus::Approved,
                        'current_stage' => null,
                        'approved_at' => $now,
                        'approved_by' => $actor->id,
                    ]);
                } else {
                    $first = $request->firstActiveApprovalStage();
                    $request->update([
                        'status' => $this->statusForApprovalStage($first),
                        'current_stage' => $first,
                        'approved_at' => $first ? null : ManilaTime::now(),
                        'approved_by' => $first ? null : $actor->id,
                    ]);
                }
            }

            $this->recordAction(
                $request,
                $actor,
                $first ?? LeaveApprovalStage::CeoFinalApproval,
                'submitted',
                null,
                null,
                $request->status,
                'Official business request submitted.'
            );
            $this->audit->log($actor, 'official_business_submitted', 'OfficialBusinessRequest', $request->id, "{$requester->fullName()} submitted {$request->reference_number}.");

            return $request->fresh([
                'employee.department',
                'participants.employee.department',
                'assignments.user',
                'actions.user',
            ]);
        });
    }

    public function afterSubmit(OfficialBusinessRequest $request): void
    {
        $request->loadMissing(['employee.user', 'assignments.user', 'participants.employee.user']);
        $this->notifier->submitted($request);

        if ($request->current_stage && $request->current_stage !== LeaveApprovalStage::ImmediateSupervisor) {
            $this->notifier->stageReady($request, $request->current_stage);
        }

        if ($request->status === OfficialBusinessStatus::Approved) {
            $approver = User::query()->find($request->approved_by) ?? $request->submitter;
            if ($approver) {
                $this->emailNotifications->officialBusinessApproved($request, $approver);
            }
        }

        $this->syncAttendanceIfApproved($request->fresh(['participants']));
    }

    public function decide(OfficialBusinessRequest $request, User $actor, LeaveDecision $decision, string $reason = '', ?string $signature = null): OfficialBusinessRequest
    {
        if ($actor->employee?->id === $request->employee_id) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot endorse or approve your own official business request.',
            ]);
        }

        if (! $request->status?->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'This official business request is no longer awaiting action.',
            ]);
        }

        $stage = $request->current_stage;
        if (! $stage) {
            throw ValidationException::withMessages(['decision' => 'This official business request has no active approval stage.']);
        }

        if ($stage === LeaveApprovalStage::CeoFinalApproval) {
            $assignedFinal = $request->assignments()
                ->where('stage', LeaveApprovalStage::CeoFinalApproval->value)
                ->where('user_id', $actor->id)
                ->where('status', 'pending')
                ->exists();

            if (! $assignedFinal) {
                throw ValidationException::withMessages([
                    'decision' => 'You are not authorized to perform final approval on this official business request.',
                ]);
            }
        }

        if ($decision === LeaveDecision::Denied && trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when rejecting an official business request.',
            ]);
        }

        $previous = $request->status;

        return DB::transaction(function () use ($request, $actor, $decision, $reason, $signature, $stage, $previous) {
            $assignment = $request->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment || ! $assignment->isPending()) {
                throw ValidationException::withMessages([
                    'decision' => 'You are not an authorized pending endorser for the current stage.',
                ]);
            }

            $assignment->update([
                'status' => $decision->value,
                'reason' => $reason !== '' ? $reason : null,
                'signature' => $signature,
                'acted_at' => ManilaTime::now(),
            ]);

            $request->refresh()->load('assignments');
            $outcome = $this->evaluateStage($request, $stage);

            if ($outcome === 'pending') {
                if (
                    $stage->usesCentralEndorsementRules()
                    && $request->snapshotEndorsementModeForStage($stage) === EndorsementMode::Sequential
                    && $decision === LeaveDecision::Approved
                ) {
                    $this->endorsement->promoteNextSequentialEndorser($request->assignments, $stage);
                }

                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, $request->status, $reason);
                $this->notifier->decisionToRequester(
                    $request,
                    'Official business endorsement update',
                    "{$actor->name} {$decision->label()} official business {$request->reference_number}. Parallel endorsement is still in progress.",
                    $decision === LeaveDecision::Denied ? 'warning' : 'info'
                );

                return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'participants.employee']);
            }

            if ($outcome === 'denied') {
                $request->update([
                    'status' => OfficialBusinessStatus::Denied,
                    'current_stage' => $stage,
                ]);
                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, OfficialBusinessStatus::Denied, $reason);
                $this->audit->log($actor, 'official_business_denied', 'OfficialBusinessRequest', $request->id, "{$request->reference_number} was rejected at {$stage->shortLabel()}.");
                $this->notifier->decisionToRequester($request, 'Official business rejected', "Official business {$request->reference_number} was rejected.", 'error');
                $this->emailNotifications->officialBusinessRejected($request, $actor, $reason !== '' ? $reason : 'No reason was provided.');

                return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'participants.employee']);
            }

            $mixed = $outcome === 'approved_mixed';
            $next = $request->nextApprovalStageAfter($stage);

            if ($next) {
                $status = $mixed && $stage->isParallel()
                    ? OfficialBusinessStatus::PartiallyApproved
                    : $this->statusForApprovalStage($next);
                $request->update([
                    'status' => $status,
                    'current_stage' => $next,
                ]);
                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, $status, $reason);
                $this->notifier->decisionToRequester(
                    $request,
                    $mixed ? 'Official business partially endorsed' : 'Official business endorsement update',
                    "{$stage->shortLabel()} completed for {$request->reference_number}. It is now with {$next->shortLabel()}.",
                    $mixed ? 'warning' : 'success'
                );
                $this->notifier->stageReady($request->fresh(['assignments.user', 'employee.user']), $next);
                $this->emailNotifications->officialBusinessWorkflowUpdate(
                    $request->fresh(['employee.user']),
                    $actor,
                    $mixed ? 'Official business partially endorsed' : 'Official business endorsement update',
                    "{$stage->shortLabel()} completed for {$request->reference_number}. It is now with {$next->shortLabel()}.",
                    $status->label(),
                    'official_business:'.$request->id.':stage:'.$stage->value.':advanced'
                );
            } else {
                $now = ManilaTime::now();
                $request->update([
                    'status' => OfficialBusinessStatus::Approved,
                    'current_stage' => $stage,
                    'approved_at' => $now,
                    'approved_by' => $actor->id,
                ]);
                $this->recordAction($request, $actor, $stage, 'decision', $decision, $previous, OfficialBusinessStatus::Approved, $reason);
                $this->notifier->approved($request->fresh(['employee.user', 'participants.employee.user']));
                $this->emailNotifications->officialBusinessApproved($request->fresh(['employee.user']), $actor);
                $this->syncAttendanceIfApproved($request);
            }

            $this->audit->log($actor, 'official_business_'.$decision->value, 'OfficialBusinessRequest', $request->id, "{$actor->name} {$decision->value} {$request->reference_number}.");

            return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'participants.employee']);
        });
    }

    public function cancel(OfficialBusinessRequest $request, User $actor, string $reason = ''): OfficialBusinessRequest
    {
        if (! $request->canBeCancelledByEmployee() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'official_business' => 'This official business request can no longer be cancelled.',
            ]);
        }

        if ($actor->employee?->id !== $request->employee_id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'official_business' => 'You can only cancel your own official business requests.',
            ]);
        }

        $previous = $request->status;

        return DB::transaction(function () use ($request, $actor, $reason, $previous) {
            $request->update([
                'status' => OfficialBusinessStatus::Cancelled,
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
                OfficialBusinessStatus::Cancelled,
                $reason
            );
            $this->audit->log($actor, 'official_business_cancelled', 'OfficialBusinessRequest', $request->id, "{$actor->name} cancelled {$request->reference_number}.");
            $this->notifier->cancelled($request->fresh(['employee.user', 'assignments.user', 'participants.employee.user']));
            $this->attendanceSync->clearCancelledRequest($request->fresh(['participants']));

            return $request->fresh(['employee.user', 'assignments.user', 'actions.user', 'participants.employee']);
        });
    }

    public function refreshStuckApprovalQueues(): void
    {
        OfficialBusinessRequest::query()
            ->where('status', OfficialBusinessStatus::PendingApproverAssignment)
            ->each(fn (OfficialBusinessRequest $request) => $this->centralApproval->resyncOfficialBusinessToCentralSettings($request));
    }

    public function pendingFor(User $user)
    {
        return OfficialBusinessRequest::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'pending'))
            ->whereHas('assignments', function ($assignment) use ($user) {
                $assignment->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('official_business_approval_assignments.stage', 'official_business_requests.current_stage');
            })
            ->with(['employee.department', 'participants.employee', 'assignments'])
            ->latest('submitted_at')
            ->tap(fn ($query) => DepartmentApprovalScope::constrainToApproverDepartments(
                $query,
                $user,
                ApprovalTransactionType::OfficialBusiness,
            ));
    }

    public function historyFor(User $user)
    {
        return OfficialBusinessRequest::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('acted_at'))
            ->with(['employee.department', 'participants.employee', 'assignments'])
            ->latest('submitted_at')
            ->tap(fn ($query) => DepartmentApprovalScope::constrainToApproverDepartments(
                $query,
                $user,
                ApprovalTransactionType::OfficialBusiness,
            ));
    }

    public function userCanAct(User $user, OfficialBusinessRequest $request): bool
    {
        if ($user->employee?->id === $request->employee_id) {
            return false;
        }

        if (! $request->status?->isOpen() || ! $request->current_stage) {
            return false;
        }

        if (! DepartmentApprovalScope::approverCanAccessRequestDepartment($user, $request->department_id)) {
            return false;
        }

        if ($request->current_stage === LeaveApprovalStage::CeoFinalApproval) {
            return $request->assignments()
                ->where('stage', LeaveApprovalStage::CeoFinalApproval->value)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();
        }

        return $request->assignments()
            ->where('stage', $request->current_stage->value)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function userIsAssignedApprover(User $user): bool
    {
        return OfficialBusinessApprovalAssignment::query()->where('user_id', $user->id)->exists();
    }

    public function dashboardCounts(?Employee $employee = null): array
    {
        $base = OfficialBusinessRequest::query();
        $mine = $employee ? (clone $base)->ownedByEmployee($employee) : null;
        $scope = $mine ?? $base;

        $pendingStatuses = [
            OfficialBusinessStatus::PendingSupervisor,
            OfficialBusinessStatus::PendingDepartmentHead,
            OfficialBusinessStatus::PendingAdministrativeHead,
            OfficialBusinessStatus::PendingCeoFinalApproval,
            OfficialBusinessStatus::PartiallyApproved,
            OfficialBusinessStatus::PendingApproverAssignment,
        ];

        return [
            'total' => (clone $scope)->count(),
            'pending' => (clone $scope)->whereIn('status', $pendingStatuses)->count(),
            'approved' => (clone $scope)->where('status', OfficialBusinessStatus::Approved)->count(),
            'rejected' => (clone $scope)->where('status', OfficialBusinessStatus::Denied)->count(),
            'cancelled' => (clone $scope)->where('status', OfficialBusinessStatus::Cancelled)->count(),
            'completed' => (clone $scope)->where('status', OfficialBusinessStatus::Completed)->count(),
        ];
    }

    /** @return 'pending'|'approved'|'approved_mixed'|'denied' */
    private function evaluateStage(OfficialBusinessRequest $request, LeaveApprovalStage $stage): string
    {
        $rows = $request->assignments->where('stage', $stage)->values();
        $active = $rows->reject(fn (OfficialBusinessApprovalAssignment $row) => $row->status === 'skipped');
        $denied = $active->filter->isDenied();
        $approved = $active->filter->isApproved();
        $total = $active->count();

        if ($total === 0) {
            return 'approved';
        }

        if ($stage->usesCentralEndorsementRules()) {
            return $this->endorsement->evaluateEndorsementStage(
                $rows,
                $request->snapshotEndorsementModeForStage($stage),
                $request->snapshotParallelRuleForStage($stage),
            );
        }

        if ($denied->isNotEmpty()) {
            return 'denied';
        }
        if ($approved->isNotEmpty()) {
            return 'approved';
        }

        return 'pending';
    }

    private function recordAction(
        OfficialBusinessRequest $request,
        User $actor,
        LeaveApprovalStage $stage,
        string $action,
        ?LeaveDecision $decision,
        ?OfficialBusinessStatus $previous,
        OfficialBusinessStatus $next,
        ?string $reason = null,
        ?string $signature = null
    ): void {
        OfficialBusinessApprovalAction::query()->create([
            'official_business_request_id' => $request->id,
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
            'signature' => $signature,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 1000),
            'acted_at' => ManilaTime::now(),
        ]);
    }

    private function nextNumber(): string
    {
        $year = ManilaTime::now()->year;
        $prefix = "OB-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $latest = OfficialBusinessRequest::query()
                ->where('reference_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference_number')
                ->value('reference_number');

            $sequence = $latest ? ((int) substr($latest, -6)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }

    /** @param  list<int>  $employeeIds @param  list<string>  $externalNames */
    private function syncParticipants(OfficialBusinessRequest $request, array $employeeIds, array $externalNames): void
    {
        $request->participants()->delete();

        $sort = 0;
        $employees = Employee::query()
            ->active()
            ->whereIn('id', $employeeIds)
            ->get()
            ->keyBy('id');

        foreach ($employeeIds as $employeeId) {
            $employee = $employees->get($employeeId);
            if (! $employee) {
                continue;
            }

            $request->participants()->create([
                'employee_id' => $employee->id,
                'participant_name' => $employee->fullName(),
                'participant_type' => 'employee',
                'sort_order' => $sort++,
            ]);
        }

        foreach ($externalNames as $name) {
            $request->participants()->create([
                'employee_id' => null,
                'participant_name' => $name,
                'participant_type' => 'external',
                'sort_order' => $sort++,
            ]);
        }
    }

    /**
     * @return array{employee_ids: list<int>, external_names: list<string>}
     */
    private function resolveParticipants(Employee $requester, array $data, bool $strict): array
    {
        $employeeIds = collect($data['participant_employee_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (! empty($data['include_requester_as_participant'])) {
            if (! in_array($requester->id, $employeeIds, true)) {
                $employeeIds[] = $requester->id;
            }
        }

        $externalNames = collect($data['external_participants'] ?? [])
            ->map(function ($row) {
                if (is_string($row)) {
                    return trim($row);
                }
                if (is_array($row)) {
                    return trim((string) ($row['name'] ?? ''));
                }

                return '';
            })
            ->filter()
            ->unique(fn (string $name) => mb_strtolower($name))
            ->values()
            ->all();

        $total = count($employeeIds) + count($externalNames);
        if ($total > 10) {
            throw ValidationException::withMessages([
                'participants' => 'You may list at most 10 participants (employees and external combined).',
            ]);
        }

        if (count($employeeIds) !== count(array_unique($employeeIds))) {
            throw ValidationException::withMessages([
                'participant_employee_ids' => 'Duplicate employee participants are not allowed.',
            ]);
        }

        if ($employeeIds !== []) {
            $valid = Employee::query()->active()->whereIn('id', $employeeIds)->pluck('id')->all();
            if (count($valid) !== count($employeeIds)) {
                throw ValidationException::withMessages([
                    'participant_employee_ids' => 'One or more selected employees are not active or eligible.',
                ]);
            }
        }

        if ($strict && $employeeIds === [] && $externalNames === []) {
            throw ValidationException::withMessages([
                'participants' => 'Add at least one participant for this official business request.',
            ]);
        }

        foreach ($externalNames as $name) {
            if (mb_strlen($name) > 255) {
                throw ValidationException::withMessages([
                    'external_participants' => 'Each external participant name must be 255 characters or fewer.',
                ]);
            }
        }

        return [
            'employee_ids' => array_values($employeeIds),
            'external_names' => $externalNames,
        ];
    }

    /** @return array<string, mixed> */
    private function validatedPayload(array $data, bool $strict): array
    {
        if ($strict && empty($data['purpose'])) {
            throw ValidationException::withMessages(['purpose' => 'Purpose is required.']);
        }

        if ($strict && (empty($data['date_covered_start']) || empty($data['date_covered_end']))) {
            throw ValidationException::withMessages(['date_covered_start' => 'Covered dates are required.']);
        }

        if (! empty($data['date_covered_start']) && ! empty($data['date_covered_end']) && $data['date_covered_end'] < $data['date_covered_start']) {
            throw ValidationException::withMessages(['date_covered_end' => 'End date must be on or after the start date.']);
        }

        $transport = filled($data['transportation_mode'] ?? null)
            ? TravelTransportation::from($data['transportation_mode'])
            : (filled($data['transportation'] ?? null) ? TravelTransportation::from($data['transportation']) : null);

        if ($transport === TravelTransportation::Other && $strict && trim((string) ($data['transportation_other'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'transportation_other' => 'Please specify the transportation mode.',
            ]);
        }

        return [
            'official_station' => $data['official_station'] ?? null,
            'purpose' => $data['purpose'] ?? '',
            'purpose_particular' => $data['purpose_particular'] ?? null,
            'destination_from' => $data['destination_from'] ?? null,
            'destination_to' => $data['destination_to'] ?? null,
            'date_covered_start' => $data['date_covered_start'] ?? ManilaTime::todayDate(),
            'date_covered_end' => $data['date_covered_end'] ?? ManilaTime::todayDate(),
            'departure_time' => $data['departure_time'] ?? null,
            'transportation_mode' => $transport?->value,
            'transportation_other' => $data['transportation_other'] ?? null,
            'vehicle_id' => filled($data['vehicle_id'] ?? null) ? (int) $data['vehicle_id'] : null,
            'vehicle_name' => $data['vehicle_name'] ?? null,
            'plate_number' => $data['plate_number'] ?? null,
            'accommodation_type' => $data['accommodation_type'] ?? null,
            'accommodation_particular' => $data['accommodation_particular'] ?? null,
            'trip_ticket_id' => filled($data['trip_ticket_id'] ?? null) ? (int) $data['trip_ticket_id'] : null,
        ];
    }

    private function assertCanCreate(Employee $requester, User $actor): void
    {
        if ($actor->employee?->id !== $requester->id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'employee' => 'You can only create official business requests from your own employee account.',
            ]);
        }
    }

    private function assertRequesterOwnsDraft(OfficialBusinessRequest $request, Employee $requester): void
    {
        if ($request->employee_id !== $requester->id) {
            throw ValidationException::withMessages(['official_business' => 'You can only edit your own official business requests.']);
        }

        if (! $request->canBeEditedByEmployee()) {
            throw ValidationException::withMessages(['official_business' => 'This official business request can no longer be edited.']);
        }
    }

    /** Uses the same stage → status mapping as travel orders (shared central workflow). */
    private function statusForApprovalStage(?LeaveApprovalStage $stage): OfficialBusinessStatus
    {
        if ($stage === null) {
            return OfficialBusinessStatus::Approved;
        }

        return OfficialBusinessStatus::from($stage->pendingStatusForTravel()->value);
    }

    private function syncAttendanceIfApproved(OfficialBusinessRequest $request): void
    {
        $request->refresh();
        if (in_array($request->status, [OfficialBusinessStatus::Approved, OfficialBusinessStatus::Completed], true)) {
            $this->attendanceSync->applyApprovedRequest($request->fresh(['participants']));
        }
    }
}
