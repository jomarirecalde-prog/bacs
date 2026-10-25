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

    public function canonicalConfiguration(): ApprovalWorkflowConfiguration
    {
        return $this->configurationFor(ApprovalTransactionType::LeaveApplication);
    }

    /** @return list<ApprovalTransactionType> */
    public function outOfSyncTransactionTypes(int $departmentId): array
    {
        $canonical = $this->canonicalConfiguration();
        $signature = $this->departmentWorkflowSignature($canonical, $departmentId);

        $outOfSync = [];
        foreach (ApprovalTransactionType::cases() as $type) {
            if ($type === ApprovalTransactionType::LeaveApplication) {
                continue;
            }

            $config = $this->configurationFor($type);
            if ($this->departmentWorkflowSignature($config, $departmentId) !== $signature) {
                $outOfSync[] = $type;
            }
        }

        return $outOfSync;
    }

    public function configurationFor(ApprovalTransactionType $type): ApprovalWorkflowConfiguration
    {
        return ApprovalWorkflowConfiguration::forType($type)
            ->load(['assignees.employee.department', 'assignees.department', 'departmentSettings']);
    }

    /** @param  array<string, mixed>  $payload @return array{configuration: ApprovalWorkflowConfiguration, reassignment: array<string, int>} */
    public function update(ApprovalTransactionType $type, User $actor, array $payload): array
    {
        $parsed = $this->parseUpdatePayload($payload);

        return DB::transaction(fn () => $this->applyConfigurationUpdate($type, $actor, $parsed));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{configuration: ApprovalWorkflowConfiguration, reassignment: array<string, int>}
     */
    public function updateUnified(User $actor, array $payload): array
    {
        $parsed = $this->parseUpdatePayload($payload);

        return DB::transaction(function () use ($actor, $parsed) {
            $lastConfiguration = $this->canonicalConfiguration();
            $aggregated = [
                'endorsement_tasks' => 0,
                'final_tasks' => 0,
                'total' => 0,
                'transactions' => 0,
            ];

            foreach (ApprovalTransactionType::cases() as $type) {
                $result = $this->applyConfigurationUpdate(
                    $type,
                    $actor,
                    $parsed,
                    'Updated unified approval workflow for department #'.$parsed['department_id'].'.',
                );

                $lastConfiguration = $result['configuration'];
                foreach (['endorsement_tasks', 'final_tasks', 'total', 'transactions'] as $key) {
                    $aggregated[$key] += (int) ($result['reassignment'][$key] ?? 0);
                }
            }

            return [
                'configuration' => $lastConfiguration,
                'reassignment' => $aggregated,
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     department_id: int,
     *     endorsement_enabled: bool,
     *     secondary_endorsement_enabled: bool,
     *     final_approval_enabled: bool,
     *     endorser_ids: Collection<int, int>,
     *     secondary_endorser_ids: Collection<int, int>,
     *     final_approver_id: ?int,
     *     endorsement_mode: EndorsementMode,
     *     endorsement_parallel_rule: LeaveParallelRule,
     *     endorser_active: Collection<string|int, bool>,
     *     secondary_endorser_active: Collection<string|int, bool>,
     * }
     */
    private function parseUpdatePayload(array $payload): array
    {
        $endorsementEnabled = (bool) ($payload['endorsement_enabled'] ?? false);
        $secondaryEndorsementEnabled = (bool) ($payload['secondary_endorsement_enabled'] ?? false);
        $finalEnabled = (bool) ($payload['final_approval_enabled'] ?? false);

        $endorserIds = collect($payload['endorser_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $secondaryEndorserIds = collect($payload['secondary_endorser_ids'] ?? [])
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

        if ($secondaryEndorsementEnabled && $secondaryEndorserIds->isEmpty()) {
            throw ValidationException::withMessages([
                'secondary_endorser_ids' => 'Select at least one second endorser or disable second endorsement.',
            ]);
        }

        if ($finalEnabled && ! $finalId) {
            throw ValidationException::withMessages([
                'final_approver_id' => 'Select a final approver or disable final approval.',
            ]);
        }

        $allEndorsers = $endorserIds->merge($secondaryEndorserIds)->unique();
        if ($finalId && $allEndorsers->contains($finalId)) {
            throw ValidationException::withMessages([
                'final_approver_id' => 'Final approver must not also be listed as an endorser.',
            ]);
        }

        if ($endorserIds->intersect($secondaryEndorserIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'secondary_endorser_ids' => 'Second endorsers must be different from the first endorsement list.',
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
        $secondaryActiveMap = collect($payload['secondary_endorser_active'] ?? [])->map(fn ($v) => (bool) $v);

        $this->assertActiveEmployees(
            $allEndorsers->all(),
            $finalId ? [$finalId] : [],
        );

        return [
            'department_id' => $departmentId,
            'endorsement_enabled' => $endorsementEnabled,
            'secondary_endorsement_enabled' => $secondaryEndorsementEnabled,
            'final_approval_enabled' => $finalEnabled,
            'endorser_ids' => $endorserIds,
            'secondary_endorser_ids' => $secondaryEndorserIds,
            'final_approver_id' => $finalId,
            'endorsement_mode' => $endorsementMode,
            'endorsement_parallel_rule' => $parallelRule,
            'endorser_active' => $activeMap,
            'secondary_endorser_active' => $secondaryActiveMap,
        ];
    }

    /**
     * @param  array{
     *     department_id: int,
     *     endorsement_enabled: bool,
     *     final_approval_enabled: bool,
     *     endorser_ids: Collection<int, int>,
     *     final_approver_id: ?int,
     *     endorsement_mode: EndorsementMode,
     *     endorsement_parallel_rule: LeaveParallelRule,
     *     endorser_active: Collection<string|int, bool>,
     * }  $parsed
     * @return array{configuration: ApprovalWorkflowConfiguration, reassignment: array<string, int>}
     */
    private function applyConfigurationUpdate(
        ApprovalTransactionType $type,
        User $actor,
        array $parsed,
        ?string $historySummary = null,
    ): array {
        $config = $this->configurationFor($type);
        $departmentId = $parsed['department_id'];
        $endorserIds = $parsed['endorser_ids'];
        $secondaryEndorserIds = $parsed['secondary_endorser_ids'];
        $finalId = $parsed['final_approver_id'];
        $activeMap = $parsed['endorser_active'];
        $secondaryActiveMap = $parsed['secondary_endorser_active'];

        $oldEndorserIds = $config->assignees()
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->orderBy('sequence_order')
            ->pluck('employee_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $oldSecondaryEndorserIds = $config->assignees()
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::SecondaryEndorser->value)
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
            $oldSecondaryEndorserIds,
            $secondaryEndorserIds->all(),
        );

        $config->update([
            'endorsement_enabled' => $parsed['endorsement_enabled'],
            'secondary_endorsement_enabled' => $parsed['secondary_endorsement_enabled'],
            'final_approval_enabled' => $parsed['final_approval_enabled'],
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

        foreach ($secondaryEndorserIds as $index => $employeeId) {
            $isActive = $secondaryActiveMap->has((string) $employeeId)
                ? (bool) $secondaryActiveMap->get((string) $employeeId)
                : ($secondaryActiveMap->has($employeeId) ? (bool) $secondaryActiveMap->get($employeeId) : true);

            ApprovalWorkflowAssignee::query()->create([
                'workflow_configuration_id' => $config->id,
                'department_id' => $departmentId,
                'employee_id' => $employeeId,
                'approval_type' => ApprovalAssigneeType::SecondaryEndorser,
                'sequence_order' => $index,
                'is_active' => $isActive,
            ]);
        }

        $this->endorsement->syncDepartmentSettings(
            $config,
            $departmentId,
            $parsed['endorsement_mode'],
            $parsed['endorsement_parallel_rule'],
        );

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
            'summary' => $historySummary ?? 'Updated '.$config->transaction_type->label().' approval workflow for department #'.$departmentId.'.',
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
    }

    public function synchronizeAllTransactionTypesFromCanonical(): void
    {
        $canonical = $this->canonicalConfiguration();
        $canonical->load(['assignees', 'departmentSettings']);

        DB::transaction(function () use ($canonical) {
            foreach (ApprovalTransactionType::cases() as $type) {
                if ($type === ApprovalTransactionType::LeaveApplication) {
                    continue;
                }

                $target = ApprovalWorkflowConfiguration::forType($type);

                $target->update([
                    'endorsement_enabled' => $canonical->endorsement_enabled,
                    'secondary_endorsement_enabled' => $canonical->secondary_endorsement_enabled,
                    'final_approval_enabled' => $canonical->final_approval_enabled,
                ]);

                $target->assignees()->delete();
                foreach ($canonical->assignees as $assignee) {
                    ApprovalWorkflowAssignee::query()->create([
                        'workflow_configuration_id' => $target->id,
                        'department_id' => $assignee->department_id,
                        'employee_id' => $assignee->employee_id,
                        'approval_type' => $assignee->approval_type,
                        'sequence_order' => $assignee->sequence_order,
                        'is_active' => $assignee->is_active,
                    ]);
                }

                $target->departmentSettings()->delete();
                foreach ($canonical->departmentSettings as $setting) {
                    ApprovalWorkflowDepartmentSetting::query()->create([
                        'workflow_configuration_id' => $target->id,
                        'department_id' => $setting->department_id,
                        'endorsement_mode' => $setting->endorsement_mode,
                        'endorsement_parallel_rule' => $setting->endorsement_parallel_rule,
                    ]);
                }
            }
        });
    }

    private function departmentWorkflowSignature(ApprovalWorkflowConfiguration $config, int $departmentId): string
    {
        $config->loadMissing(['assignees', 'departmentSettings']);

        $deptSettings = $config->departmentSettingFor($departmentId);

        $endorsers = $config->assignees
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::Endorser)
            ->sortBy('sequence_order')
            ->map(fn (ApprovalWorkflowAssignee $row) => [
                'employee_id' => $row->employee_id,
                'is_active' => $row->is_active,
            ])
            ->values()
            ->all();

        $secondaryEndorsers = $config->assignees
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::SecondaryEndorser)
            ->sortBy('sequence_order')
            ->map(fn (ApprovalWorkflowAssignee $row) => [
                'employee_id' => $row->employee_id,
                'is_active' => $row->is_active,
            ])
            ->values()
            ->all();

        $final = $config->assignees
            ->where('department_id', $departmentId)
            ->where('approval_type', ApprovalAssigneeType::FinalApprover)
            ->first();

        return json_encode([
            'endorsement_enabled' => $config->endorsement_enabled,
            'secondary_endorsement_enabled' => $config->secondary_endorsement_enabled,
            'final_approval_enabled' => $config->final_approval_enabled,
            'endorsement_mode' => $deptSettings?->endorsement_mode?->value,
            'endorsement_parallel_rule' => $deptSettings?->endorsement_parallel_rule?->value,
            'endorsers' => $endorsers,
            'secondary_endorsers' => $secondaryEndorsers,
            'final_approver_id' => $final?->employee_id,
        ], JSON_THROW_ON_ERROR);
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
            'secondary_endorsement_enabled' => $config->secondary_endorsement_enabled,
            'final_approval_enabled' => $config->final_approval_enabled,
            'version' => $config->version,
            'departments' => $config->assignees
                ->groupBy('department_id')
                ->map(function (Collection $rows, $departmentId) use ($config) {
                    $endorsers = $rows->where('approval_type', ApprovalAssigneeType::Endorser);
                    $secondaryEndorsers = $rows->where('approval_type', ApprovalAssigneeType::SecondaryEndorser);

                    return [
                        'department_id' => (int) $departmentId,
                        'department' => $rows->first()?->department?->name,
                        'endorsers' => $endorsers->map(fn (ApprovalWorkflowAssignee $row) => [
                            'employee_id' => $row->employee_id,
                            'name' => $row->employee?->fullName(),
                            'position' => $row->employee?->position,
                        ])->values()->all(),
                        'secondary_endorsers' => $secondaryEndorsers->map(fn (ApprovalWorkflowAssignee $row) => [
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

    public function secondaryEndorserCount(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): int
    {
        return $config->activeSecondaryEndorsers($departmentId)->count();
    }

    /** @return array{stage: LeaveApprovalStage|null, parallel: LeaveParallelRule, skip_hr: bool} */
    public function initialStageMeta(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): array
    {
        $endorsers = $config->endorsement_enabled ? $this->endorserCount($config, $departmentId) : 0;
        $secondaryEndorsers = $config->secondary_endorsement_enabled ? $this->secondaryEndorserCount($config, $departmentId) : 0;
        $final = $config->final_approval_enabled && $config->activeFinalApprover($departmentId);

        if ($endorsers > 0) {
            return [
                'stage' => LeaveApprovalStage::ImmediateSupervisor,
                'parallel' => $this->parallelRuleFor($config, $departmentId),
                'endorsement_mode' => $this->endorsementModeFor($config, $departmentId),
                'skip_hr' => false,
            ];
        }

        if ($secondaryEndorsers > 0) {
            return [
                'stage' => LeaveApprovalStage::DepartmentHead,
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

        if ($meta['stage'] === LeaveApprovalStage::DepartmentHead) {
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

        if ($meta['stage'] === LeaveApprovalStage::DepartmentHead) {
            return LeaveStatus::PendingDepartmentHead;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return LeaveStatus::PendingCeoFinalApproval;
        }

        return LeaveStatus::Approved;
    }

    public function travelStatusAfterBootstrap(ApprovalWorkflowConfiguration $config, ?int $departmentId = null): TravelOrderStatus
    {
        $meta = $this->initialStageMeta($config, $departmentId);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return TravelOrderStatus::PendingSupervisor;
        }

        if ($meta['stage'] === LeaveApprovalStage::DepartmentHead) {
            return TravelOrderStatus::PendingDepartmentHead;
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

        if ($meta['stage'] === LeaveApprovalStage::DepartmentHead) {
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

        if ($meta['stage'] === LeaveApprovalStage::DepartmentHead) {
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

        if (! $config->endorsement_enabled && ! $config->secondary_endorsement_enabled && ! $config->final_approval_enabled) {
            return;
        }
    }

    public function missingActionableApprovers(object $entity): bool
    {
        foreach ([LeaveApprovalStage::ImmediateSupervisor, LeaveApprovalStage::DepartmentHead, LeaveApprovalStage::CeoFinalApproval] as $stage) {
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

        if ($config->secondary_endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeSecondaryEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createLeaveAssignment(
                    $application,
                    LeaveApprovalStage::DepartmentHead,
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

        if ($config->secondary_endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeSecondaryEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createTravelAssignment(
                    $order,
                    LeaveApprovalStage::DepartmentHead,
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

        if ($config->secondary_endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeSecondaryEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createPardonAssignment(
                    $correction,
                    LeaveApprovalStage::DepartmentHead,
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

        if ($config->secondary_endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeSecondaryEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createOfficialTimeAssignment(
                    $request,
                    LeaveApprovalStage::DepartmentHead,
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

        if ($config->secondary_endorsement_enabled && $departmentId) {
            $sort = 0;
            foreach ($config->activeSecondaryEndorsers($departmentId)->orderBy('sequence_order')->get() as $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createOvertimeAssignment(
                    $request,
                    LeaveApprovalStage::DepartmentHead,
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
            ->where('stage', LeaveApprovalStage::AdministrativeHead->value)
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
            ->where('stage', LeaveApprovalStage::AdministrativeHead->value)
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

        $secondaryEndorserUserIds = [];
        if ($config->secondary_endorsement_enabled && $departmentId) {
            foreach ($config->activeSecondaryEndorsers($departmentId)->get() as $row) {
                $userId = $row->employee?->user?->id;
                if ($userId && $row->employee?->id !== $requester->id) {
                    $secondaryEndorserUserIds[] = $userId;
                }
            }
        }

        $this->reconcileLeaveStageAssignees($application, LeaveApprovalStage::DepartmentHead, $secondaryEndorserUserIds, $requester);

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

        $actor = $application->assignments()
            ->whereNotNull('acted_at')
            ->orderByDesc('acted_at')
            ->first()
            ?->user;

        if ($actor) {
            app(LeaveApplicationService::class)->finalizeAfterWorkflowComplete($application, $actor);

            return;
        }

        $application->update([
            'status' => LeaveStatus::Approved,
            'current_stage' => null,
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
