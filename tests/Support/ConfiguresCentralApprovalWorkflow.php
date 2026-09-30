<?php

namespace Tests\Support;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Models\ApprovalWorkflowAssignee;
use App\Models\ApprovalWorkflowConfiguration;

trait ConfiguresCentralApprovalWorkflow
{
    /** @param  list<int>  $endorserEmployeeIds */
    protected function resetCentralApproval(
        ApprovalTransactionType $type,
        array $endorserEmployeeIds = [],
        ?int $finalEmployeeId = null,
        bool $endorsementEnabled = true,
        bool $finalEnabled = true,
    ): ApprovalWorkflowConfiguration {
        $config = ApprovalWorkflowConfiguration::forType($type);
        $config->update([
            'endorsement_enabled' => $endorsementEnabled,
            'final_approval_enabled' => $finalEnabled,
        ]);
        $config->assignees()->delete();

        foreach (array_values(array_unique($endorserEmployeeIds)) as $index => $employeeId) {
            ApprovalWorkflowAssignee::query()->create([
                'workflow_configuration_id' => $config->id,
                'employee_id' => $employeeId,
                'approval_type' => ApprovalAssigneeType::Endorser,
                'sequence_order' => $index,
            ]);
        }

        if ($finalEmployeeId) {
            ApprovalWorkflowAssignee::query()->create([
                'workflow_configuration_id' => $config->id,
                'employee_id' => $finalEmployeeId,
                'approval_type' => ApprovalAssigneeType::FinalApprover,
                'sequence_order' => 0,
            ]);
        }

        return $config->fresh(['assignees']);
    }

    protected function appendCentralEndorser(ApprovalTransactionType $type, int $employeeId): void
    {
        $config = ApprovalWorkflowConfiguration::forType($type);

        $exists = $config->assignees()
            ->where('employee_id', $employeeId)
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->exists();

        if ($exists) {
            return;
        }

        ApprovalWorkflowAssignee::query()->create([
            'workflow_configuration_id' => $config->id,
            'employee_id' => $employeeId,
            'approval_type' => ApprovalAssigneeType::Endorser,
            'sequence_order' => $config->assignees()
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
