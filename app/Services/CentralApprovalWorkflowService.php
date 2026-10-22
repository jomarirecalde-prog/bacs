<?php

namespace App\Services;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Enums\EndorsementMode;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\LeaveDecision;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveParallelRule;
use App\Enums\LeaveStatus;
use App\Enums\TravelOrderStatus;
use App\Enums\UserRole;
use App\Models\ApprovalWorkflowAssignee;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\ApprovalWorkflowDepartmentSetting;
use App\Models\ApprovalWorkflowConfigurationHistory;
use App\Models\AttendanceCorrectionApprovalAssignment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\OfficialTimeApprovalAssignment;
use App\Models\OfficialTimeRequest;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeRequest;
use App\Enums\OfficialTimeStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\LeaveApprovalAssignment;
use App\Models\TravelOrder;
use App\Models\TravelOrderApprovalAssignment;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CentralApprovalWorkflowService
{
    public function __construct(
        private readonly LeaveWorkflowService $employeeSearch,
        private readonly NotificationService $notifications,
        private readonly EndorsementWorkflowCoordinator $endorsement,
        private readonly ApprovalWorkflowReassignmentService $reassignment,
    ) {}

    /** @return Collection<int, ApprovalWorkflowConfiguration> */
    public function allConfigurations(): Collection
    {
        return ApprovalWorkflowConfiguration::query()
            ->with(['assignees.employee.department', 'assignees.department', 'departmentSettings'])
            ->orderBy('transaction_type')
            ->get();
    }

    public function configurationFor(ApprovalTransactionType $type): ApprovalWorkflowConfiguration
    {
        return ApprovalWorkflowConfiguration::forType($type)
            ->load(['assignees.employee.department', 'assignees.department', 'departmentSettings']);
    }

    /** @param  array<string, mixed>  $payload @return array{configuration: ApprovalWorkflowConfiguration, reassignment: array<string, int>} */
    public function update(ApprovalTransactionType $type, User $actor, array $payload): array
    {
        $config = $this->configurationFor($type);
        $endorsementEnabled = (bool) ($payload['endorsement_enabled'] ?? false);
        $finalEnabled = (bool) ($payload['final_approval_enabled'] ?? false);

        $endorserIds = collect($payload['endorser_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $finalId = filled($payload['final_approver_id'] ?? null) ? (int) $payload['final_approver_id'] : null;

        if ($endorsementEnabled && $endorserIds->isEmpty()) {
            throw ValidationException::withMessages([
                'endorser_ids' => 'Select at least one endorser or disable endorsement.',
            ]);
        }

        if ($finalEnabled && ! $finalId) {
            throw ValidationException::withMessages([
                'final_approver_id' => 'Select a final approver or disable final approval.',
            ]);
        }

        if ($finalId && $endorserIds->contains($finalId)) {
            throw ValidationException::withMessages([
                'final_approver_id' => 'Final approver must not also be listed as an endorser.',
            ]);
        }

        $departmentId = (int) ($payload['department_id'] ?? 0);
        if ($departmentId <= 0) {
            throw ValidationException::withMessages([
                'department_id' => 'Select a department for this approval workflow.',
            ]);
        }

        if (! Department::query()->active()->whereKey($departmentId)->exists()) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department is not active.',
            ]);
        }

        $endorsementMode = EndorsementMode::tryFrom((string) ($payload['endorsement_mode'] ?? '')) ?? EndorsementMode::Parallel;
        $parallelRule = LeaveParallelRule::tryFrom((string) ($payload['endorsement_parallel_rule'] ?? '')) ?? LeaveParallelRule::All;
        $activeMap = collect($payload['endorser_active'] ?? [])->map(fn ($v) => (bool) $v);

        $this->assertActiveEmployees($endorserIds->all(), $finalId ? [$finalId] : []);

        $oldEndorserIds = $config->assignees()
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->orderBy('sequence_order')
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $oldFinalId = $config->assignees()
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::FinalApprover->value)
            ->value('employee_id');

        $oldFinalId = $oldFinalId ? (int) $oldFinalId : null;

        $reassignmentPlan = $this->reassignment->preview(
            $type,
            $departmentId,
            $oldEndorserIds,
            $oldFinalId,
            $endorserIds->all(),
            $finalId,
        );

        return DB::transaction(function () use ($config, $actor, $type, $endorsementEnabled, $finalEnabled, $endorserIds, $finalId, $departmentId, $endorsementMode, $parallelRule, $activeMap, $reassignmentPlan) {
            $config->update([
                'endorsement_enabled' => $endorsementEnabled,
                'final_approval_enabled' => $finalEnabled,
                'version' => $config->version + 1,
                'updated_by' => $actor->id,
            ]);

            $config->assignees()->where('department_id', $departmentId)->delete();

            foreach ($endorserIds as $index => $employeeId) {
                $isActive = $activeMap->has((string) $employeeId)
                    ? (bool) $activeMap->get((string) $employeeId)
                    : ($activeMap->has($employeeId) ? (bool) $activeMap->get($employeeId) : true);

                ApprovalWorkflowAssignee::query()->create([
                    'workflow_configuration_id' => $config->id,
                    'department_id' => $departmentId,
                    'employee_id' => $employeeId,
                    'approval_type' => ApprovalAssigneeType::Endorser,
                    'sequence_order' => $index,
                    'is_active' => $isActive,
                ]);
            }

            $this->endorsement->syncDepartmentSettings($config, $departmentId, $endorsementMode, $parallelRule);

            if ($finalId) {
                ApprovalWorkflowAssignee::query()->create([
                    'workflow_configuration_id' => $config->id,
                    'department_id' => $departmentId,
                    'employee_id' => $finalId,
                    'approval_type' => ApprovalAssigneeType::FinalApprover,
                    'sequence_order' => 0,
                ]);
            }

            $config->refresh()->load(['assignees.employee.department', 'assignees.department']);

            ApprovalWorkflowConfigurationHistory::query()->create([
                'workflow_configuration_id' => $config->id,
                'version' => $config->version,
                'snapshot' => $this->snapshot($config),
                'summary' => 'Updated '.$config->transaction_type->label().' approval workflow for department #'.$departmentId.'.',
                'updated_by' => $actor->id,
            ]);

            $reassignmentResult = $this->reassignment->apply(
                $type,
                $departmentId,
                $config,
                $reassignmentPlan,
                $actor,
                'Approval workflow configuration update',
            );

            return [
                'configuration' => $config,
                'reassignment' => $reassignmentResult,
            ];
        });
    }

    /** @param  array<string, mixed>  $payload @return array{endorser_map: array<int, int>, final_map: array<int, int>, endorsement_tasks: int, final_tasks: int, total: int} */
    public function previewReassignment(ApprovalTransactionType $type, int $departmentId, array $payload): array
    {
        $config = $this->configurationFor($type);

        $endorserIds = collect($payload['endorser_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $finalId = filled($payload['final_approver_id'] ?? null) ? (int) $payload['final_approver_id'] : null;

        $oldEndorserIds = $config->assignees()
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->orderBy('sequence_order')
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $oldFinalId = $config->assignees()
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::FinalApprover->value)
            ->value('employee_id');

        return $this->reassignment->preview(
            $type,
            $departmentId,
            $oldEndorserIds,
            $oldFinalId ? (int) $oldFinalId : null,
            $endorserIds,
            $finalId,
        );
    }

    /** @return array<string, mixed> */
    public function snapshot(ApprovalWorkflowConfiguration $config): array
    {
        $config->loadMissing('assignees.employee');

        return [
            'endorsement_enabled' => $config->endorsement_enabled,
            'final_approval_enabled' => $config->final_approval_enabled,
            'version' => $config->version,
            'departments' => $config->assignees
                ->groupBy('department_id')
                ->map(function (Collection $rows, $departmentId) use ($config) {
                    $endorsers = $rows->where('approval_type', ApprovalAssigneeType::Endorser);

                    return [
                        'department_id' => (int) $departmentId,
                        'department' => $rows->first()?->department?->name,
                        'endorsers' => $endorsers->map(fn (ApprovalWorkflowAssignee $row) => [
                            'employee_id' => $row->employee_id,
                            'name' => $row->employee?->fullName(),
                            'position' => $row->employee?->position,
                        ])->values()->all(),
                        'final_approver' => $rows->where('approval_type', ApprovalAssigneeType::FinalApprover)
                            ->map(fn (ApprovalWorkflowAssignee $row) => [
                                'employee_id' => $row->employee_id,
                                'name' => $row->employee?->fullName(),
                                'position' => $row->employee?->position,
                            ])->first(),
                        'endorsement_mode' => $config->departmentSettingFor((int) $departmentId)?->endorsement_mode?->value,
                        'endorsement_parallel_rule' => $config->departmentSettingFor((int) $departmentId)?->endorsement_parallel_rule?->value,
                    ];
                })->values()->all(),
        ];
    }

    public function parallelRuleFor(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): LeaveParallelRule
    {
        return $this->endorsement->settingsFor($config, $departmentId)->endorsement_parallel_rule ?? LeaveParallelRule::All;
    }

    public function endorsementModeFor(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): EndorsementMode
    {
        return $this->endorsement->settingsFor($config, $departmentId)->endorsement_mode ?? EndorsementMode::Parallel;
    }

    public function endorserCount(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): int
    {
        return $config->activeEndorsers($departmentId)->count();
    }

    /** @return array{stage: LeaveApprovalStage|null, parallel: LeaveParallelRule, skip_hr: bool} */
    public function initialStageMeta(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): array
    {
        $endorsers = $config->endorsement_enabled ? $this->endorserCount($config, $departmentId) : 0;
        $final = $config->final_approval_enabled && $config->activeFinalApprover($departmentId);

        if ($endorsers > 0) {
            return [
                'stage' => LeaveApprovalStage::ImmediateSupervisor,
                'parallel' => $this->parallelRuleFor($config, $departmentId),
                'endorsement_mode' => $this->endorsementModeFor($config, $departmentId),
                'skip_hr' => false,
            ];
        }

        if ($final) {
            return [
                'stage' => LeaveApprovalStage::CeoFinalApproval,
                'parallel' => LeaveParallelRule::All,
                'skip_hr' => false,
            ];
        }

        return [
            'stage' => null,
            'parallel' => LeaveParallelRule::All,
            'skip_hr' => false,
        ];
    }

    public function bootstrapLeaveApplication(LeaveApplication $application, Employee $requester): void
    {
        $config = $this->configurationFor(ApprovalTransactionType::LeaveApplication);
        $snapshot = $this->endorsement->snapshotForRequest($config, $requester->department_id);
        $application->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
            'parallel_rule' => $snapshot['parallel_rule'],
            'endorsement_mode' => $snapshot['endorsement_mode'],
        ]);

        $this->createLeaveAssignments($application, $config, $requester, $snapshot['endorsement_mode']);
    }

    public function bootstrapTravelOrder(TravelOrder $order, Employee $requester): void
    {
        $config = $this->configurationFor(ApprovalTransactionType::TravelOrder);
        $snapshot = $this->endorsement->snapshotForRequest($config, $requester->department_id);
        $order->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
            'parallel_rule' => $snapshot['parallel_rule'],
            'endorsement_mode' => $snapshot['endorsement_mode'],
        ]);

        $this->createTravelAssignments($order, $config, $requester, $snapshot['endorsement_mode']);
    }

    public function bootstrapPardon(AttendanceCorrectionRequest $request, Employee $requester): void
    {
        $config = $this->configurationFor(ApprovalTransactionType::Pardon);
        $snapshot = $this->endorsement->snapshotForRequest($config, $requester->department_id);
        $request->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
            'endorsement_mode' => $snapshot['endorsement_mode'],
            'endorsement_parallel_rule' => $snapshot['parallel_rule'],
        ]);

        $this->createPardonAssignments($request, $config, $requester, $snapshot['endorsement_mode']);
    }

    public function bootstrapOvertime(OvertimeRequest $request, Employee $requester): void
    {
        $config = $this->configurationFor(ApprovalTransactionType::OvertimeRequest);
        $snapshot = $this->endorsement->snapshotForRequest($config, $requester->department_id);
        $request->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
            'endorsement_mode' => $snapshot['endorsement_mode'],
            'endorsement_parallel_rule' => $snapshot['parallel_rule'],
        ]);

        $this->createOvertimeAssignments($request, $config, $requester, $snapshot['endorsement_mode']);
    }

    public function bootstrapOfficialTime(OfficialTimeRequest $request, Employee $requester): void
    {
        $config = $this->configurationFor(ApprovalTransactionType::OfficialTime);
        $snapshot = $this->endorsement->snapshotForRequest($config, $requester->department_id);
        $request->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
            'parallel_rule' => $snapshot['parallel_rule'],
            'endorsement_mode' => $snapshot['endorsement_mode'],
        ]);

        $this->createOfficialTimeAssignments($request, $config, $requester, $snapshot['endorsement_mode']);
    }

    public function overtimeStatusAfterBootstrap(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): OvertimeRequestStatus
    {
        $meta = $this->initialStageMeta($config, $departmentId);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return OvertimeRequestStatus::PendingEndorsement;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return OvertimeRequestStatus::PendingFinalApproval;
        }

        return OvertimeRequestStatus::Pending;
    }

    public function leaveStatusAfterBootstrap(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): LeaveStatus
    {
        $meta = $this->initialStageMeta($config, $departmentId);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return LeaveStatus::PendingSupervisor;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return LeaveStatus::PendingCeoFinalApproval;
        }

        return LeaveStatus::PendingHr;
    }

    public function travelStatusAfterBootstrap(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): TravelOrderStatus
    {
        $meta = $this->initialStageMeta($config, $departmentId);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return TravelOrderStatus::PendingSupervisor;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return TravelOrderStatus::PendingCeoFinalApproval;
        }

        return TravelOrderStatus::Approved;
    }

    public function officialTimeStatusAfterBootstrap(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): OfficialTimeStatus
    {
        $meta = $this->initialStageMeta($config, $departmentId);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return OfficialTimeStatus::PendingSupervisor;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return OfficialTimeStatus::PendingCeoFinalApproval;
        }

        return OfficialTimeStatus::Approved;
    }

    public function pardonStatusAfterBootstrap(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): AttendanceCorrectionStatus
    {
        $meta = $this->initialStageMeta($config, $departmentId);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return AttendanceCorrectionStatus::PendingEndorsement;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return AttendanceCorrectionStatus::PendingFinalApproval;
        }

        return AttendanceCorrectionStatus::Pending;
    }

    public function leaveStageAfterBootstrap(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): ?LeaveApprovalStage
    {
        return $this->initialStageMeta($config, $departmentId)['stage'];
    }

    public function assertCanSubmit(ApprovalTransactionType $type, ?int $departmentId): void
    {
        if ($departmentId === null) {
            throw ValidationException::withMessages([
                'transaction' => 'Your employee profile must have an assigned department before submitting '.$type->label().'.',
            ]);
        }

        $config = $this->configurationFor($type);

        if (! $config->endorsement_enabled && ! $config->final_approval_enabled) {
            return;
        }
    }

    public function missingActionableApprovers(object $entity): bool
    {
        foreach ([LeaveApprovalStage::ImmediateSupervisor, LeaveApprovalStage::CeoFinalApproval] as $stage) {
            if ($this->assignmentsForStage($entity, $stage)->isNotEmpty()) {
                return false;
            }
        }

        return true;
    }

    public function notifyPendingApproverAssignment(
        ApprovalTransactionType $type,
        Employee $requester,
        string $reference,
    ): void {
        $requester->loadMissing('department');
        $department = $requester->department?->name ?? 'Unknown department';

        $this->notifications->notifyAdmins(
            $type->label().' awaiting approver assignment',
            "{$reference} from {$requester->fullName()} ({$department}) has no department approver configured. Assign one under Settings → Approval Workflow.",
            'warn',
            route('admin.settings.index').'#approval-workflow',
        );
    }

    /** @return \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model> */
    private function assignmentsForStage(object $entity, LeaveApprovalStage $stage): Collection
    {
        if ($entity instanceof LeaveApplication) {
            return $entity->assignments()->where('stage', $stage->value)->get();
        }

        if ($entity instanceof TravelOrder) {
            return $entity->assignments()->where('stage', $stage->value)->get();
        }

        if ($entity instanceof AttendanceCorrectionRequest) {
            return $entity->approvalAssignments()->where('stage', $stage->value)->get();
        }

        if ($entity instanceof OvertimeRequest) {
            return $entity->approvalAssignments()->where('stage', $stage->value)->get();
        }

        if ($entity instanceof OfficialTimeRequest) {
            return $entity->assignments()->where('stage', $stage->value)->get();
        }

        return collect();
    }

    private function createLeaveAssignments(
        LeaveApplication $application,
        ApprovalWorkflowConfiguration $config,
        Employee $requester,
        EndorsementMode $mode,
    ): void {
        $departmentId = $requester->department_id;

        if ($config->endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createLeaveAssignment(
                    $application,
                    LeaveApprovalStage::ImmediateSupervisor,
                    $user,
                    $sort,
                    $this->endorsement->initialAssignmentStatus($mode, $sort),
                );
                $sort++;
            }
        }

        if ($config->final_approval_enabled && $departmentId) {
            $final = $config->activeFinalApprover($departmentId);
            $user = $final?->employee?->user;
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createLeaveAssignment($application, LeaveApprovalStage::CeoFinalApproval, $user, 0);
            }
        }
    }

    private function createTravelAssignments(
        TravelOrder $order,
        ApprovalWorkflowConfiguration $config,
        Employee $requester,
        EndorsementMode $mode,
    ): void {
        $departmentId = $requester->department_id;

        if ($config->endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createTravelAssignment(
                    $order,
                    LeaveApprovalStage::ImmediateSupervisor,
                    $user,
                    $sort,
                    $this->endorsement->initialAssignmentStatus($mode, $sort),
                );
                $sort++;
            }
        }

        if ($config->final_approval_enabled && $departmentId) {
            $final = $config->activeFinalApprover($departmentId);
            $user = $final?->employee?->user;
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createTravelAssignment($order, LeaveApprovalStage::CeoFinalApproval, $user, 0);
            }
        }
    }

    private function createPardonAssignments(
        AttendanceCorrectionRequest $correction,
        ApprovalWorkflowConfiguration $config,
        Employee $requester,
        EndorsementMode $mode,
    ): void {
        $departmentId = $requester->department_id;

        if ($config->endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createPardonAssignment(
                    $correction,
                    LeaveApprovalStage::ImmediateSupervisor,
                    $user,
                    $sort,
                    $this->endorsement->initialAssignmentStatus($mode, $sort),
                );
                $sort++;
            }
        }

        if ($config->final_approval_enabled && $departmentId) {
            $final = $config->activeFinalApprover($departmentId);
            $user = $final?->employee?->user;
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createPardonAssignment($correction, LeaveApprovalStage::CeoFinalApproval, $user, 0);
            }
        }
    }

    private function snapshotLeaveHrOfficers(LeaveApplication $application, Employee $employee): void
    {
        User::query()
            ->where('role', UserRole::Admin)
            ->where('status', 'active')
            ->get()
            ->each(function (User $user, int $index) use ($application, $employee) {
                if ($user->employee?->id === $employee->id) {
                    return;
                }

                $this->createLeaveAssignment($application, LeaveApprovalStage::HrOfficer, $user, $index);
            });
    }

    private function createLeaveAssignment(
        LeaveApplication $application,
        LeaveApprovalStage $stage,
        User $user,
        int $sort,
        string $status = 'pending',
    ): void {
        LeaveApprovalAssignment::query()->create([
            'leave_application_id' => $application->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'approver_role' => $user->role?->value,
            'status' => $status,
            'sort_order' => $sort,
        ]);
    }

    private function createTravelAssignment(
        TravelOrder $order,
        LeaveApprovalStage $stage,
        User $user,
        int $sort,
        string $status = 'pending',
    ): void {
        TravelOrderApprovalAssignment::query()->create([
            'travel_order_id' => $order->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'approver_role' => $user->role?->value,
            'status' => $status,
            'sort_order' => $sort,
        ]);
    }

    private function createOfficialTimeAssignments(
        OfficialTimeRequest $request,
        ApprovalWorkflowConfiguration $config,
        Employee $requester,
        EndorsementMode $mode,
    ): void {
        $departmentId = $requester->department_id;

        if ($config->endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createOfficialTimeAssignment(
                    $request,
                    LeaveApprovalStage::ImmediateSupervisor,
                    $user,
                    $sort,
                    $this->endorsement->initialAssignmentStatus($mode, $sort),
                );
                $sort++;
            }
        }

        if ($config->final_approval_enabled && $departmentId) {
            $final = $config->activeFinalApprover($departmentId);
            $user = $final?->employee?->user;
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createOfficialTimeAssignment($request, LeaveApprovalStage::CeoFinalApproval, $user, 0);
            }
        }
    }

    private function createOfficialTimeAssignment(
        OfficialTimeRequest $request,
        LeaveApprovalStage $stage,
        User $user,
        int $sort,
        string $status = 'pending',
    ): void {
        OfficialTimeApprovalAssignment::query()->create([
            'official_time_request_id' => $request->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'approver_role' => $user->role?->value,
            'status' => $status,
            'sort_order' => $sort,
        ]);
    }

    private function createPardonAssignment(
        AttendanceCorrectionRequest $correction,
        LeaveApprovalStage $stage,
        User $user,
        int $sort,
        string $status = 'pending',
    ): void {
        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'status' => $status,
            'sort_order' => $sort,
        ]);
    }

    private function createOvertimeAssignments(
        OvertimeRequest $request,
        ApprovalWorkflowConfiguration $config,
        Employee $requester,
        EndorsementMode $mode,
    ): void {
        $departmentId = $requester->department_id;

        if ($config->endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createOvertimeAssignment(
                    $request,
                    LeaveApprovalStage::ImmediateSupervisor,
                    $user,
                    $sort,
                    $this->endorsement->initialAssignmentStatus($mode, $sort),
                );
                $sort++;
            }
        }

        if ($config->final_approval_enabled && $departmentId) {
            $final = $config->activeFinalApprover($departmentId);
            $user = $final?->employee?->user;
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createOvertimeAssignment($request, LeaveApprovalStage::CeoFinalApproval, $user, 0);
            }
        }
    }

    private function createOvertimeAssignment(
        OvertimeRequest $request,
        LeaveApprovalStage $stage,
        User $user,
        int $sort,
        string $status = 'pending',
    ): void {
        OvertimeApprovalAssignment::query()->create([
            'overtime_request_id' => $request->id,
            'stage' => $stage->value,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'status' => $status,
            'sort_order' => $sort,
        ]);
    }

    /** @param  list<int>  $endorserIds
     * @param  list<int>  $finalIds
     */
    private function assertActiveEmployees(array $endorserIds, array $finalIds): void
    {
        $ids = array_values(array_unique(array_merge($endorserIds, $finalIds)));
        if ($ids === []) {
            return;
        }

        $valid = Employee::query()->active()->whereIn('id', $ids)->pluck('id')->all();
        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages([
                'endorser_ids' => 'One or more selected employees are not active.',
            ]);
        }
    }

    public function leaveApplicationNeedsCentralResync(LeaveApplication $application): bool
    {
        if (! $application->status?->isOpen()) {
            return false;
        }

        if ($application->central_approval_config_id === null) {
            return true;
        }

        return $application->assignments()
            ->whereIn('stage', [
                LeaveApprovalStage::DepartmentHead->value,
                LeaveApprovalStage::AdministrativeHead->value,
            ])
            ->exists();
    }

    public function resyncLeaveApplicationToCentralSettings(LeaveApplication $application): bool
    {
        if (! $this->leaveApplicationNeedsCentralResync($application)) {
            return false;
        }

        $application->loadMissing(['employee', 'assignments']);
        $employee = $application->employee;
        if (! $employee) {
            return false;
        }

        $this->removeLegacyDepartmentLeaveAssignments($application);

        if (in_array($application->status, [LeaveStatus::PendingHr, LeaveStatus::PartiallyApproved], true)) {
            $config = $this->configurationFor(ApprovalTransactionType::LeaveApplication);
            $application->update([
                'central_approval_config_id' => $config->id,
                'central_approval_config_version' => $config->version,
                'parallel_rule' => $this->parallelRuleFor($config, $employee->department_id),
            ]);

            return true;
        }

        $config = $this->configurationFor(ApprovalTransactionType::LeaveApplication);
        $hadActions = $application->assignments()->whereNotNull('acted_at')->exists();

        if (! $hadActions) {
            $application->assignments()->delete();
            $this->bootstrapLeaveApplication($application, $employee);
        } else {
            $application->update([
                'central_approval_config_id' => $config->id,
                'central_approval_config_version' => $config->version,
                'parallel_rule' => $this->parallelRuleFor($config, $employee->department_id),
            ]);
            $this->reconcileLeaveCentralAssignments($application, $config, $employee);
        }

        $this->refreshLeaveApplicationStageAfterResync($application);

        return true;
    }

    private function removeLegacyDepartmentLeaveAssignments(LeaveApplication $application): void
    {
        $application->assignments()
            ->whereIn('stage', [
                LeaveApprovalStage::DepartmentHead->value,
                LeaveApprovalStage::AdministrativeHead->value,
            ])
            ->delete();
    }

    private function reconcileLeaveCentralAssignments(
        LeaveApplication $application,
        ApprovalWorkflowConfiguration $config,
        Employee $requester,
    ): void {
        $departmentId = $requester->department_id;
        $endorserUserIds = [];
        if ($config->endorsement_enabled && $departmentId) {
            foreach ($config->activeEndorsers($departmentId)->get() as $row) {
                $userId = $row->employee?->user?->id;
                if ($userId && $row->employee?->id !== $requester->id) {
                    $endorserUserIds[] = $userId;
                }
            }
        }

        $this->reconcileLeaveStageAssignees($application, LeaveApprovalStage::ImmediateSupervisor, $endorserUserIds, $requester);

        $finalUserIds = [];
        if ($config->final_approval_enabled && $departmentId) {
            $final = $config->activeFinalApprover($departmentId);
            $userId = $final?->employee?->user?->id;
            if ($userId && $final?->employee?->id !== $requester->id) {
                $finalUserIds[] = $userId;
            }
        }

        $this->reconcileLeaveStageAssignees($application, LeaveApprovalStage::CeoFinalApproval, $finalUserIds, $requester);
    }

    /** @param  list<int>  $allowedUserIds */
    private function reconcileLeaveStageAssignees(
        LeaveApplication $application,
        LeaveApprovalStage $stage,
        array $allowedUserIds,
        Employee $requester,
    ): void {
        $allowed = collect($allowedUserIds)->unique()->values();

        if ($allowed->isEmpty()) {
            $application->assignments()->where('stage', $stage->value)->delete();

            return;
        }

        $application->assignments()
            ->where('stage', $stage->value)
            ->whereNotIn('user_id', $allowed->all())
            ->delete();

        foreach ($allowed as $index => $userId) {
            $exists = $application->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $userId)
                ->exists();

            if ($exists) {
                continue;
            }

            $user = User::query()->find($userId);
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createLeaveAssignment($application, $stage, $user, $index);
            }
        }
    }

    private function refreshLeaveApplicationStageAfterResync(LeaveApplication $application): void
    {
        $application->refresh()->load('assignments');
        $employee = $application->employee;
        if (! $employee) {
            return;
        }

        foreach ($application->activeApprovalStageSequence() as $stage) {
            if ($stage === LeaveApprovalStage::HrOfficer) {
                continue;
            }

            $outcome = $application->stageDecision($stage);

            if ($outcome === null || $outcome === 'mixed') {
                $application->update([
                    'status' => $stage->pendingStatus(),
                    'current_stage' => $stage,
                ]);

                return;
            }

            if ($outcome === LeaveDecision::Denied->value) {
                return;
            }
        }

        $this->snapshotLeaveHrOfficers($application, $employee);
        $application->update([
            'status' => LeaveStatus::PendingHr,
            'current_stage' => LeaveApprovalStage::HrOfficer,
        ]);
    }

    public function resyncTravelOrderToCentralSettings(TravelOrder $order): bool
    {
        if (! $order->status?->isOpen()) {
            return false;
        }

        if ($order->assignments()->whereNotNull('acted_at')->exists()) {
            return false;
        }

        $order->loadMissing('requester');
        $requester = $order->requester;
        if (! $requester) {
            return false;
        }

        $order->assignments()->delete();
        $this->bootstrapTravelOrder($order, $requester);
        $order->load('assignments');

        $config = $this->configurationFor(ApprovalTransactionType::TravelOrder);
        $meta = $this->initialStageMeta($config);

        if ($meta['stage'] === null) {
            $order->update([
                'status' => TravelOrderStatus::Approved,
                'current_stage' => null,
            ]);

            return true;
        }

        $first = $order->firstActiveApprovalStage();
        $order->update([
            'status' => $first?->pendingStatusForTravel() ?? TravelOrderStatus::Approved,
            'current_stage' => $first,
        ]);

        return true;
    }

    public function searchEmployees(string $term): Collection
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
            ->limit(20)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->fullName(),
                'position' => $employee->position,
                'department' => $employee->department?->name,
            ]);
    }
}
