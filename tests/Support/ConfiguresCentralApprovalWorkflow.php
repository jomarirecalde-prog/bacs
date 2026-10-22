<?php

namespace Tests\Support;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Enums\EndorsementMode;
use App\Enums\LeaveParallelRule;
use App\Models\ApprovalWorkflowAssignee;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\ApprovalWorkflowDepartmentSetting;

trait ConfiguresCentralApprovalWorkflow
{
    /** @param  list<int>  $endorserEmployeeIds */
    protected function resetCentralApproval(
        ApprovalTransactionType $type,
        array $endorserEmployeeIds = [],
        ?int $finalEmployeeId = null,
        bool $endorsementEnabled = true,
        bool $finalEnabled = true,
        int $departmentId = 1,
        EndorsementMode $endorsementMode = EndorsementMode::Parallel,
        LeaveParallelRule $parallelRule = LeaveParallelRule::All,
    ): ApprovalWorkflowConfiguration {
        $config = ApprovalWorkflowConfiguration::forType($type);
        $config->update([
            'endorsement_enabled' => $endorsementEnabled,
            'final_approval_enabled' => $finalEnabled,
        ]);
        $config->assignees()->where('department_id', $departmentId)->delete();

        foreach (array_values(array_unique($endorserEmployeeIds)) as $index => $employeeId) {
            ApprovalWorkflowAssignee::query()->create([
                'workflow_configuration_id' => $config->id,
                'department_id' => $departmentId,
                'employee_id' => $employeeId,
                'approval_type' => ApprovalAssigneeType::Endorser,
                'sequence_order' => $index,
            ]);
        }

        if ($finalEmployeeId) {
            ApprovalWorkflowAssignee::query()->create([
                'workflow_configuration_id' => $config->id,
                'department_id' => $departmentId,
                'employee_id' => $finalEmployeeId,
                'approval_type' => ApprovalAssigneeType::FinalApprover,
                'sequence_order' => 0,
            ]);
        }

        ApprovalWorkflowDepartmentSetting::query()->updateOrCreate(
            [
                'workflow_configuration_id' => $config->id,
                'department_id' => $departmentId,
            ],
            [
                'endorsement_mode' => $endorsementMode,
                'endorsement_parallel_rule' => $parallelRule,
            ]
        );

        return $config->fresh(['assignees', 'departmentSettings']);
    }

    protected function appendCentralEndorser(ApprovalTransactionType $type, int $employeeId, int $departmentId = 1): void
    {
        $config = ApprovalWorkflowConfiguration::forType($type);

        $exists = $config->assignees()
            ->where('department_id', $departmentId)
            ->where('employee_id', $employeeId)
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->exists();

        if ($exists) {
            return;
        }

        ApprovalWorkflowAssignee::query()->create([
            'workflow_configuration_id' => $config->id,
            'department_id' => $departmentId,
            'employee_id' => $employeeId,
            'approval_type' => ApprovalAssigneeType::Endorser,
            'sequence_order' => $config->assignees()
                ->where('department_id', $departmentId)
                ->where('approval_type', ApprovalAssigneeType::Endorser->value)
                ->count(),
        ]);
    }

    protected function seedDefaultCentralApprovalsFromCeo(int $ceoEmployeeId): void
    {
        foreach (ApprovalTransactionType::cases() as $type) {
            $this->resetCentralApproval($type, [], $ceoEmployeeId);
        }
    }
}
