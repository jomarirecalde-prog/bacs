<?php

namespace App\Services;

use App\Enums\ApprovalTransactionType;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveStatus;
use App\Enums\OfficialTimeStatus;
use App\Enums\OvertimeRequestStatus;
use App\Enums\TravelOrderStatus;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\ApprovalWorkflowReassignmentLog;
use App\Models\AttendanceCorrectionApprovalAssignment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalAssignment;
use App\Models\OfficialTimeApprovalAssignment;
use App\Models\OfficialTimeRequest;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeRequest;
use App\Models\TravelOrder;
use App\Models\TravelOrderApprovalAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ApprovalWorkflowReassignmentService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param  list<int>  $oldEndorserIds
     * @param  list<int>  $newEndorserIds
     * @return array{
     *     endorser_map: array<int, int>,
     *     final_map: array<int, int>,
     *     endorsement_tasks: int,
     *     final_tasks: int,
     *     total: int,
     * }
     */
    public function preview(
        ApprovalTransactionType $type,
        int $departmentId,
        array $oldEndorserIds,
        ?int $oldFinalId,
        array $newEndorserIds,
        ?int $newFinalId,
        array $oldSecondaryEndorserIds = [],
        array $newSecondaryEndorserIds = [],
    ): array {
        $endorserMap = $this->buildEndorserReplacementMap($oldEndorserIds, $newEndorserIds);
        $secondaryEndorserMap = $this->buildEndorserReplacementMap($oldSecondaryEndorserIds, $newSecondaryEndorserIds);
        $finalMap = $this->buildFinalReplacementMap($oldFinalId, $newFinalId);

        $this->validateReplacementEmployees($departmentId, $endorserMap, $finalMap);
        $this->validateReplacementEmployees($departmentId, $secondaryEndorserMap, []);

        $endorsementTasks = $this->countTasks($type, $departmentId, $endorserMap, LeaveApprovalStage::ImmediateSupervisor);
        $secondaryTasks = $this->countTasks($type, $departmentId, $secondaryEndorserMap, LeaveApprovalStage::DepartmentHead);
        $finalTasks = $this->countTasks($type, $departmentId, $finalMap, LeaveApprovalStage::CeoFinalApproval);

        return [
            'endorser_map' => $endorserMap,
            'secondary_endorser_map' => $secondaryEndorserMap,
            'final_map' => $finalMap,
            'endorsement_tasks' => $endorsementTasks + $secondaryTasks,
            'final_tasks' => $finalTasks,
            'total' => $endorsementTasks + $secondaryTasks + $finalTasks,
        ];
    }

    /**
     * @param  array{endorser_map: array<int, int>, final_map: array<int, int>}  $plan
     * @return array{endorsement_tasks: int, final_tasks: int, total: int, transactions: int}
     */
    public function apply(
        ApprovalTransactionType $type,
        int $departmentId,
        ApprovalWorkflowConfiguration $config,
        array $plan,
        User $actor,
        string $reason = 'Approval workflow configuration update',
    ): array {
        $endorserMap = $plan['endorser_map'] ?? [];
        $secondaryEndorserMap = $plan['secondary_endorser_map'] ?? [];
        $finalMap = $plan['final_map'] ?? [];

        if ($endorserMap === [] && $secondaryEndorserMap === [] && $finalMap === []) {
            return ['endorsement_tasks' => 0, 'final_tasks' => 0, 'total' => 0, 'transactions' => 0];
        }

        $this->validateReplacementEmployees($departmentId, $endorserMap, $finalMap);
        $this->validateReplacementEmployees($departmentId, $secondaryEndorserMap, []);

        $endorsementTasks = 0;
        $finalTasks = 0;
        $touchedParents = [];

        DB::transaction(function () use (
            $type,
            $departmentId,
            $config,
            $endorserMap,
            $secondaryEndorserMap,
            $finalMap,
            $actor,
            $reason,
            &$endorsementTasks,
            &$finalTasks,
            &$touchedParents,
        ) {
            foreach ($this->definitions()[$type->value]['handlers'] as $handler) {
                $query = $handler['query']($departmentId);
                $query->whereNotNull('central_approval_config_id');

                foreach ($query->cursor() as $parent) {
                    /** @var Model $parent */
                    $parentId = (int) $parent->getKey();
                    $assignmentModel = $handler['assignment_model'];
                    $parentFk = $handler['parent_fk'];
                    $currentStage = $this->resolveStage($parent, $handler['stage_column']);

                    if (! $currentStage) {
                        continue;
                    }

                    foreach ($endorserMap as $oldEmployeeId => $newEmployeeId) {
                        if ($currentStage !== LeaveApprovalStage::ImmediateSupervisor) {
                            continue;
                        }

                        $count = $this->reassignForEmployee(
                            $type,
                            $departmentId,
                            $config,
                            $assignmentModel,
                            $parentFk,
                            $parent,
                            $oldEmployeeId,
                            $newEmployeeId,
                            LeaveApprovalStage::ImmediateSupervisor,
                            $actor,
                            $reason,
                        );

                        if ($count > 0) {
                            $endorsementTasks += $count;
                            $touchedParents[$parent::class.':'.$parentId] = true;
                        }
                    }

                    foreach ($secondaryEndorserMap as $oldEmployeeId => $newEmployeeId) {
                        if ($currentStage !== LeaveApprovalStage::DepartmentHead) {
                            continue;
                        }

                        $count = $this->reassignForEmployee(
                            $type,
                            $departmentId,
                            $config,
                            $assignmentModel,
                            $parentFk,
                            $parent,
                            $oldEmployeeId,
                            $newEmployeeId,
                            LeaveApprovalStage::DepartmentHead,
                            $actor,
                            $reason,
                        );

                        if ($count > 0) {
                            $endorsementTasks += $count;
                            $touchedParents[$parent::class.':'.$parentId] = true;
                        }
                    }

                    foreach ($finalMap as $oldEmployeeId => $newEmployeeId) {
                        if ($currentStage !== LeaveApprovalStage::CeoFinalApproval) {
                            continue;
                        }

                        $count = $this->reassignForEmployee(
                            $type,
                            $departmentId,
                            $config,
                            $assignmentModel,
                            $parentFk,
                            $parent,
                            $oldEmployeeId,
                            $newEmployeeId,
                            LeaveApprovalStage::CeoFinalApproval,
                            $actor,
                            $reason,
                        );

                        if ($count > 0) {
                            $finalTasks += $count;
                            $touchedParents[$parent::class.':'.$parentId] = true;
                        }
                    }
                }
            }
        });

        $this->notifyNewAssignees($type, $endorserMap, $finalMap, $endorsementTasks + $finalTasks);

        return [
            'endorsement_tasks' => $endorsementTasks,
            'final_tasks' => $finalTasks,
            'total' => $endorsementTasks + $finalTasks,
            'transactions' => count($touchedParents),
        ];
    }

    /** @param  list<int>  $oldOrderedIds @param  list<int>  $newOrderedIds @return array<int, int> */
    public function buildEndorserReplacementMap(array $oldOrderedIds, array $newOrderedIds): array
    {
        $map = [];
        $max = max(count($oldOrderedIds), count($newOrderedIds));

        for ($i = 0; $i < $max; $i++) {
            $oldId = $oldOrderedIds[$i] ?? null;
            $newId = $newOrderedIds[$i] ?? null;
            if ($oldId && $newId && $oldId !== $newId) {
                $map[(int) $oldId] = (int) $newId;
            }
        }

        $removed = array_values(array_diff($oldOrderedIds, $newOrderedIds));
        $added = array_values(array_diff($newOrderedIds, $oldOrderedIds));

        foreach ($removed as $index => $oldId) {
            $oldId = (int) $oldId;
            if (isset($map[$oldId])) {
                continue;
            }
            if (isset($added[$index])) {
                $map[$oldId] = (int) $added[$index];
            }
        }

        return $map;
    }

    /** @return array<int, int> */
    public function buildFinalReplacementMap(?int $oldFinalId, ?int $newFinalId): array
    {
        if (! $oldFinalId || ! $newFinalId || $oldFinalId === $newFinalId) {
            return [];
        }

        return [(int) $oldFinalId => (int) $newFinalId];
    }

    /** @param  array<int, int>  $endorserMap @param  array<int, int>  $finalMap */
    private function validateReplacementEmployees(int $departmentId, array $endorserMap, array $finalMap): void
    {
        $employeeIds = array_unique(array_merge(array_values($endorserMap), array_values($finalMap)));

        foreach ($employeeIds as $employeeId) {
            $employee = Employee::query()->find($employeeId);
            if (! $employee?->user_id) {
                throw ValidationException::withMessages([
                    'endorser_ids' => 'Replacement approver must have an active user account.',
                ]);
            }

            if ((int) $employee->department_id !== $departmentId) {
                throw ValidationException::withMessages([
                    'department_id' => 'Replacement approvers must belong to the selected department.',
                ]);
            }
        }
    }

    /** @param  array<int, int>  $employeeMap */
    private function countTasks(
        ApprovalTransactionType $type,
        int $departmentId,
        array $employeeMap,
        LeaveApprovalStage $requiredParentStage,
    ): int {
        if ($employeeMap === []) {
            return 0;
        }

        $count = 0;

        foreach ($this->definitions()[$type->value]['handlers'] as $handler) {
            $query = $handler['query']($departmentId);
            $query->whereNotNull('central_approval_config_id');

            foreach ($query->cursor() as $parent) {
                if ($this->resolveStage($parent, $handler['stage_column']) !== $requiredParentStage) {
                    continue;
                }

                $assignmentModel = $handler['assignment_model'];
                $parentFk = $handler['parent_fk'];
                $parentId = (int) $parent->getKey();

                foreach (array_keys($employeeMap) as $oldEmployeeId) {
                    $count += $assignmentModel::query()
                        ->where($parentFk, $parentId)
                        ->where('stage', $requiredParentStage->value)
                        ->where('employee_id', $oldEmployeeId)
                        ->whereIn('status', $this->actionableAssignmentStatuses())
                        ->count();
                }
            }
        }

        return $count;
    }

    /**
     * @param  class-string<Model>  $assignmentModel
     */
    private function reassignForEmployee(
        ApprovalTransactionType $type,
        int $departmentId,
        ApprovalWorkflowConfiguration $config,
        string $assignmentModel,
        string $parentFk,
        Model $parent,
        int $oldEmployeeId,
        int $newEmployeeId,
        LeaveApprovalStage $stage,
        User $actor,
        string $reason,
    ): int {
        $newEmployee = Employee::query()->with('user')->find($newEmployeeId);
        if (! $newEmployee?->user) {
            return 0;
        }

        $assignments = $assignmentModel::query()
            ->where($parentFk, $parent->getKey())
            ->where('stage', $stage->value)
            ->where('employee_id', $oldEmployeeId)
            ->whereIn('status', $this->actionableAssignmentStatuses())
            ->lockForUpdate()
            ->get();

        if ($assignments->isEmpty()) {
            return 0;
        }

        $reassigned = 0;

        foreach ($assignments as $assignment) {
            $previousStatus = (string) $assignment->status;
            $previousUserId = $assignment->user_id;

            $duplicate = $assignmentModel::query()
                ->where($parentFk, $parent->getKey())
                ->where('stage', $stage->value)
                ->where('user_id', $newEmployee->user_id)
                ->where('id', '!=', $assignment->id)
                ->first();

            if ($duplicate) {
                if (in_array($duplicate->status, $this->actionableAssignmentStatuses(), true)) {
                    $this->retireAssignment($assignment);

                    ApprovalWorkflowReassignmentLog::query()->create([
                        'workflow_configuration_id' => $config->id,
                        'transaction_type' => $type->value,
                        'department_id' => $departmentId,
                        'assignable_type' => $parent::class,
                        'assignable_id' => $parent->getKey(),
                        'assignment_id' => $assignment->id,
                        'stage' => $stage->value,
                        'previous_user_id' => $previousUserId,
                        'previous_employee_id' => $oldEmployeeId,
                        'new_user_id' => $newEmployee->user_id,
                        'new_employee_id' => $newEmployee->id,
                        'previous_assignment_status' => $previousStatus,
                        'new_assignment_status' => 'deduplicated',
                        'reason' => $reason,
                        'initiated_by' => $actor->id,
                    ]);

                    continue;
                }
            }

            $assignment->update([
                'user_id' => $newEmployee->user_id,
                'employee_id' => $newEmployee->id,
                'approver_name' => $newEmployee->fullName() ?: $newEmployee->user->name,
                'approver_position' => $newEmployee->position,
            ]);

            ApprovalWorkflowReassignmentLog::query()->create([
                'workflow_configuration_id' => $config->id,
                'transaction_type' => $type->value,
                'department_id' => $departmentId,
                'assignable_type' => $parent::class,
                'assignable_id' => $parent->getKey(),
                'assignment_id' => $assignment->id,
                'stage' => $stage->value,
                'previous_user_id' => $previousUserId,
                'previous_employee_id' => $oldEmployeeId,
                'new_user_id' => $newEmployee->user_id,
                'new_employee_id' => $newEmployee->id,
                'previous_assignment_status' => $previousStatus,
                'new_assignment_status' => $previousStatus,
                'reason' => $reason,
                'initiated_by' => $actor->id,
            ]);

            $reassigned++;
        }

        return $reassigned;
    }

    private function retireAssignment(Model $assignment): void
    {
        if (in_array('skipped', $this->supportedStatuses($assignment), true)) {
            $assignment->update(['status' => 'skipped']);

            return;
        }

        if ($assignment->isPending() || (method_exists($assignment, 'isWaiting') && $assignment->isWaiting())) {
            $assignment->delete();
        }
    }

    /** @return list<string> */
    private function supportedStatuses(Model $assignment): array
    {
        return ['pending', 'waiting', 'approved', 'denied', 'skipped'];
    }

    /** @return list<string> */
    private function actionableAssignmentStatuses(): array
    {
        return ['pending', 'waiting'];
    }

    private function resolveStage(Model $parent, string $column): ?LeaveApprovalStage
    {
        $value = $parent->{$column};

        if ($value instanceof LeaveApprovalStage) {
            return $value;
        }

        return LeaveApprovalStage::tryFrom((string) $value);
    }

    /** @param  array<int, int>  $endorserMap @param  array<int, int>  $finalMap */
    private function notifyNewAssignees(
        ApprovalTransactionType $type,
        array $endorserMap,
        array $finalMap,
        int $taskCount,
    ): void {
        if ($taskCount === 0) {
            return;
        }

        $newEmployeeIds = array_unique(array_merge(array_values($endorserMap), array_values($finalMap)));
        $route = $this->pendingRouteFor($type);

        foreach ($newEmployeeIds as $employeeId) {
            $user = Employee::query()->find($employeeId)?->user;
            if (! $user) {
                continue;
            }

            try {
                $this->notifications->notify(
                    $user,
                    'Approval tasks reassigned to you',
                    'Pending '.$type->label().' approval task(s) were transferred to you after a workflow configuration update.',
                    'info',
                    $route,
                );
            } catch (\Throwable $exception) {
                Log::warning('approval_reassignment_notification_failed', [
                    'user_id' => $user->id,
                    'transaction_type' => $type->value,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function pendingRouteFor(ApprovalTransactionType $type): string
    {
        return match ($type) {
            ApprovalTransactionType::LeaveApplication => route('leave.approvals.index'),
            ApprovalTransactionType::Pardon => route('pardon.approvals.index'),
            ApprovalTransactionType::TravelOrder => route('travel-order.approvals.index'),
            ApprovalTransactionType::OvertimeRequest => route('overtime.approvals.index'),
            ApprovalTransactionType::OfficialTime => route('official-time.approvals.index'),
        };
    }

    /** @return array<string, array{handlers: list<array<string, mixed>>}> */
    private function definitions(): array
    {
        return [
            ApprovalTransactionType::LeaveApplication->value => [
                'handlers' => [[
                    'query' => fn (int $departmentId) => LeaveApplication::query()
                        ->where('department_id', $departmentId)
                        ->whereIn('status', $this->openLeaveStatuses()),
                    'assignment_model' => LeaveApprovalAssignment::class,
                    'parent_fk' => 'leave_application_id',
                    'stage_column' => 'current_stage',
                ]],
            ],
            ApprovalTransactionType::Pardon->value => [
                'handlers' => [[
                    'query' => fn (int $departmentId) => AttendanceCorrectionRequest::query()
                        ->whereIn('status', $this->openPardonStatuses())
                        ->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId)),
                    'assignment_model' => AttendanceCorrectionApprovalAssignment::class,
                    'parent_fk' => 'attendance_correction_request_id',
                    'stage_column' => 'current_approval_stage',
                ]],
            ],
            ApprovalTransactionType::TravelOrder->value => [
                'handlers' => [[
                    'query' => fn (int $departmentId) => TravelOrder::query()
                        ->whereIn('status', $this->openTravelStatuses())
                        ->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId)),
                    'assignment_model' => TravelOrderApprovalAssignment::class,
                    'parent_fk' => 'travel_order_id',
                    'stage_column' => 'current_stage',
                ]],
            ],
            ApprovalTransactionType::OvertimeRequest->value => [
                'handlers' => [[
                    'query' => fn (int $departmentId) => OvertimeRequest::query()
                        ->whereIn('status', $this->openOvertimeStatuses())
                        ->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId)),
                    'assignment_model' => OvertimeApprovalAssignment::class,
                    'parent_fk' => 'overtime_request_id',
                    'stage_column' => 'current_approval_stage',
                ]],
            ],
            ApprovalTransactionType::OfficialTime->value => [
                'handlers' => [[
                    'query' => fn (int $departmentId) => OfficialTimeRequest::query()
                        ->whereIn('status', $this->openOfficialTimeStatuses())
                        ->whereHas('employee', fn ($q) => $q->where('department_id', $departmentId)),
                    'assignment_model' => OfficialTimeApprovalAssignment::class,
                    'parent_fk' => 'official_time_request_id',
                    'stage_column' => 'current_stage',
                ]],
            ],
        ];
    }

    /** @return list<string> */
    private function openLeaveStatuses(): array
    {
        return collect(LeaveStatus::cases())->filter->isOpen()->map->value->values()->all();
    }

    /** @return list<string> */
    private function openPardonStatuses(): array
    {
        return collect(AttendanceCorrectionStatus::cases())->filter->isOpen()->map->value->values()->all();
    }

    /** @return list<string> */
    private function openTravelStatuses(): array
    {
        return collect(TravelOrderStatus::cases())->filter->isOpen()->map->value->values()->all();
    }

    /** @return list<string> */
    private function openOvertimeStatuses(): array
    {
        return OvertimeRequestStatus::openValues();
    }

    /** @return list<string> */
    private function openOfficialTimeStatuses(): array
    {
        return collect(OfficialTimeStatus::cases())->filter->isOpen()->map->value->values()->all();
    }
}
