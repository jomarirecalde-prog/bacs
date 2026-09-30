<?php

namespace App\Services;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\LeaveDecision;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveParallelRule;
use App\Enums\LeaveStatus;
use App\Enums\TravelOrderStatus;
use App\Enums\UserRole;
use App\Models\ApprovalWorkflowAssignee;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\ApprovalWorkflowConfigurationHistory;
use App\Models\AttendanceCorrectionApprovalAssignment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalAssignment;
use App\Models\TravelOrder;
use App\Models\TravelOrderApprovalAssignment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CentralApprovalWorkflowService
{
    public function __construct(private readonly LeaveWorkflowService $employeeSearch) {}

    /** @return Collection<int, ApprovalWorkflowConfiguration> */
    public function allConfigurations(): Collection
    {
        return ApprovalWorkflowConfiguration::query()
            ->with(['assignees.employee.department'])
            ->orderBy('transaction_type')
            ->get();
    }

    public function configurationFor(ApprovalTransactionType $type): ApprovalWorkflowConfiguration
    {
        return ApprovalWorkflowConfiguration::forType($type)
            ->load(['assignees.employee.department']);
    }

    /** @param  array<string, mixed>  $payload */
    public function update(ApprovalTransactionType $type, User $actor, array $payload): ApprovalWorkflowConfiguration
    {
        $config = $this->configurationFor($type);
        $endorsementEnabled = (bool) ($payload['endorsement_enabled'] ?? false);
        $finalEnabled = (bool) ($payload['final_approval_enabled'] ?? false);
        $confirmAuto = (bool) ($payload['confirm_auto_approval'] ?? false);

        if (! $endorsementEnabled && ! $finalEnabled && ! $confirmAuto) {
            throw ValidationException::withMessages([
                'confirm_auto_approval' => 'Confirm automatic approval when both endorsement and final approval are disabled.',
            ]);
        }

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

        $this->assertActiveEmployees($endorserIds->all(), $finalId ? [$finalId] : []);

        return DB::transaction(function () use ($config, $actor, $endorsementEnabled, $finalEnabled, $endorserIds, $finalId) {
            $previous = $this->snapshot($config);
            $config->update([
                'endorsement_enabled' => $endorsementEnabled,
                'final_approval_enabled' => $finalEnabled,
                'version' => $config->version + 1,
                'updated_by' => $actor->id,
            ]);

            $config->assignees()->delete();

            foreach ($endorserIds as $index => $employeeId) {
                ApprovalWorkflowAssignee::query()->create([
                    'workflow_configuration_id' => $config->id,
                    'employee_id' => $employeeId,
                    'approval_type' => ApprovalAssigneeType::Endorser,
                    'sequence_order' => $index,
                ]);
            }

            if ($finalId) {
                ApprovalWorkflowAssignee::query()->create([
                    'workflow_configuration_id' => $config->id,
                    'employee_id' => $finalId,
                    'approval_type' => ApprovalAssigneeType::FinalApprover,
                    'sequence_order' => 0,
                ]);
            }

            $config->refresh()->load('assignees.employee.department');

            ApprovalWorkflowConfigurationHistory::query()->create([
                'workflow_configuration_id' => $config->id,
                'version' => $config->version,
                'snapshot' => $this->snapshot($config),
                'summary' => 'Updated '.$config->transaction_type->label().' approval workflow.',
                'updated_by' => $actor->id,
            ]);

            return $config;
        });
    }

    /** @return array<string, mixed> */
    public function snapshot(ApprovalWorkflowConfiguration $config): array
    {
        $config->loadMissing('assignees.employee');

        return [
            'endorsement_enabled' => $config->endorsement_enabled,
            'final_approval_enabled' => $config->final_approval_enabled,
            'version' => $config->version,
            'endorsers' => $config->assignees
                ->where('approval_type', ApprovalAssigneeType::Endorser)
                ->map(fn (ApprovalWorkflowAssignee $row) => [
                    'employee_id' => $row->employee_id,
                    'name' => $row->employee?->fullName(),
                    'position' => $row->employee?->position,
                ])->values()->all(),
            'final_approver' => $config->assignees
                ->where('approval_type', ApprovalAssigneeType::FinalApprover)
                ->map(fn (ApprovalWorkflowAssignee $row) => [
                    'employee_id' => $row->employee_id,
                    'name' => $row->employee?->fullName(),
                    'position' => $row->employee?->position,
                ])->first(),
        ];
    }

    public function parallelRuleFor(ApprovalWorkflowConfiguration $config): LeaveParallelRule
    {
        $count = $config->assignees->where('approval_type', ApprovalAssigneeType::Endorser)->count();

        return $count > 1 ? LeaveParallelRule::All : LeaveParallelRule::All;
    }

    public function endorserCount(ApprovalWorkflowConfiguration $config): int
    {
        return $config->activeEndorsers()->count();
    }

    /** @return array{stage: LeaveApprovalStage|null, parallel: LeaveParallelRule, skip_hr: bool} */
    public function initialStageMeta(ApprovalWorkflowConfiguration $config): array
    {
        $endorsers = $config->endorsement_enabled ? $this->endorserCount($config) : 0;
        $final = $config->final_approval_enabled && $config->activeFinalApprover();

        if ($endorsers > 0) {
            return [
                'stage' => LeaveApprovalStage::ImmediateSupervisor,
                'parallel' => $this->parallelRuleFor($config),
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
        $application->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
            'parallel_rule' => $this->parallelRuleFor($config),
        ]);

        $this->createLeaveAssignments($application, $config, $requester);
    }

    public function bootstrapTravelOrder(TravelOrder $order, Employee $requester): void
    {
        $config = $this->configurationFor(ApprovalTransactionType::TravelOrder);
        $order->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
            'parallel_rule' => $this->parallelRuleFor($config),
        ]);

        $this->createTravelAssignments($order, $config, $requester);
    }

    public function bootstrapPardon(AttendanceCorrectionRequest $request, Employee $requester): void
    {
        $config = $this->configurationFor(ApprovalTransactionType::Pardon);
        $request->update([
            'central_approval_config_id' => $config->id,
            'central_approval_config_version' => $config->version,
        ]);

        $this->createPardonAssignments($request, $config, $requester);
    }

    public function leaveStatusAfterBootstrap(ApprovalWorkflowConfiguration $config): LeaveStatus
    {
        $meta = $this->initialStageMeta($config);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return LeaveStatus::PendingSupervisor;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return LeaveStatus::PendingCeoFinalApproval;
        }

        return LeaveStatus::PendingHr;
    }

    public function travelStatusAfterBootstrap(ApprovalWorkflowConfiguration $config): TravelOrderStatus
    {
        $meta = $this->initialStageMeta($config);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return TravelOrderStatus::PendingSupervisor;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return TravelOrderStatus::PendingCeoFinalApproval;
        }

        return TravelOrderStatus::Approved;
    }

    public function pardonStatusAfterBootstrap(ApprovalWorkflowConfiguration $config): AttendanceCorrectionStatus
    {
        $meta = $this->initialStageMeta($config);

        if ($meta['stage'] === LeaveApprovalStage::ImmediateSupervisor) {
            return AttendanceCorrectionStatus::PendingEndorsement;
        }

        if ($meta['stage'] === LeaveApprovalStage::CeoFinalApproval) {
            return AttendanceCorrectionStatus::PendingFinalApproval;
        }

        return AttendanceCorrectionStatus::Pending;
    }

    public function leaveStageAfterBootstrap(ApprovalWorkflowConfiguration $config): ?LeaveApprovalStage
    {
        return $this->initialStageMeta($config)['stage'];
    }

    public function assertCanSubmit(ApprovalTransactionType $type): void
    {
        $config = $this->configurationFor($type);
        $endorsers = $config->endorsement_enabled ? $this->endorserCount($config) : 0;
        $final = $config->final_approval_enabled && $config->activeFinalApprover();

        if ($endorsers === 0 && ! $final && ! ($config->endorsement_enabled === false && $config->final_approval_enabled === false)) {
            throw ValidationException::withMessages([
                'transaction' => 'Approval workflow for '.$type->label().' is incomplete. Configure it under Settings → Approval Workflow.',
            ]);
        }
    }

    private function createLeaveAssignments(LeaveApplication $application, ApprovalWorkflowConfiguration $config, Employee $requester): void
    {
        if ($config->endorsement_enabled) {
            foreach ($config->activeEndorsers()->get() as $index => $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createLeaveAssignment($application, LeaveApprovalStage::ImmediateSupervisor, $user, $index);
            }
        }

        if ($config->final_approval_enabled) {
            $final = $config->activeFinalApprover();
            $user = $final?->employee?->user;
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createLeaveAssignment($application, LeaveApprovalStage::CeoFinalApproval, $user, 0);
            }
        }
    }

    private function createTravelAssignments(TravelOrder $order, ApprovalWorkflowConfiguration $config, Employee $requester): void
    {
        if ($config->endorsement_enabled) {
            foreach ($config->activeEndorsers()->get() as $index => $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createTravelAssignment($order, LeaveApprovalStage::ImmediateSupervisor, $user, $index);
            }
        }

        if ($config->final_approval_enabled) {
            $final = $config->activeFinalApprover();
            $user = $final?->employee?->user;
            if ($user && $user->employee?->id !== $requester->id) {
                $this->createTravelAssignment($order, LeaveApprovalStage::CeoFinalApproval, $user, 0);
            }
        }
    }

    private function createPardonAssignments(AttendanceCorrectionRequest $correction, ApprovalWorkflowConfiguration $config, Employee $requester): void
    {
        if ($config->endorsement_enabled) {
            foreach ($config->activeEndorsers()->get() as $index => $row) {
                $user = $row->employee?->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }
                $this->createPardonAssignment($correction, LeaveApprovalStage::ImmediateSupervisor, $user, $index);
            }
        }

        if ($config->final_approval_enabled) {
            $final = $config->activeFinalApprover();
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

    private function createLeaveAssignment(LeaveApplication $application, LeaveApprovalStage $stage, User $user, int $sort): void
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

    private function createTravelAssignment(TravelOrder $order, LeaveApprovalStage $stage, User $user, int $sort): void
    {
        TravelOrderApprovalAssignment::query()->create([
            'travel_order_id' => $order->id,
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

    private function createPardonAssignment(AttendanceCorrectionRequest $correction, LeaveApprovalStage $stage, User $user, int $sort): void
    {
        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'status' => 'pending',
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
                'parallel_rule' => $this->parallelRuleFor($config),
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
                'parallel_rule' => $this->parallelRuleFor($config),
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
        $endorserUserIds = [];
        if ($config->endorsement_enabled) {
            foreach ($config->activeEndorsers()->get() as $row) {
                $userId = $row->employee?->user?->id;
                if ($userId && $row->employee?->id !== $requester->id) {
                    $endorserUserIds[] = $userId;
                }
            }
        }

        $this->reconcileLeaveStageAssignees($application, LeaveApprovalStage::ImmediateSupervisor, $endorserUserIds, $requester);

        $finalUserIds = [];
        if ($config->final_approval_enabled) {
            $final = $config->activeFinalApprover();
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
