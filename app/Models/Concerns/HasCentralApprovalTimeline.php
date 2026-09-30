<?php

namespace App\Models\Concerns;

use App\Enums\LeaveApprovalStage;

trait HasCentralApprovalTimeline
{
    public function usesCentralApprovalWorkflow(): bool
    {
        return filled($this->central_approval_config_id);
    }

    /** @return list<LeaveApprovalStage> */
    public function approvalTimelineStages(): array
    {
        if ($this->usesCentralApprovalWorkflow() || ! $this->usesLegacyDepartmentApprovalStages()) {
            return $this->centralApprovalTimelineStages();
        }

        return LeaveApprovalStage::sequence();
    }

    public function approvalTimelineUsesCentralLabels(): bool
    {
        return $this->usesCentralApprovalWorkflow() || ! $this->usesLegacyDepartmentApprovalStages();
    }

    /** @return list<LeaveApprovalStage> */
    protected function centralApprovalTimelineStages(): array
    {
        $ordered = [
            LeaveApprovalStage::ImmediateSupervisor,
            LeaveApprovalStage::CeoFinalApproval,
        ];

        $stages = array_values(array_filter(
            $ordered,
            fn (LeaveApprovalStage $stage) => $this->assignmentsFor($stage)->isNotEmpty()
        ));

        if ($stages !== []) {
            return $stages;
        }

        $hr = $this->assignmentsFor(LeaveApprovalStage::HrOfficer);
        if ($hr->isNotEmpty()) {
            return [LeaveApprovalStage::HrOfficer];
        }

        return [];
    }

    protected function usesLegacyDepartmentApprovalStages(): bool
    {
        foreach ([LeaveApprovalStage::DepartmentHead, LeaveApprovalStage::AdministrativeHead] as $stage) {
            if ($this->assignmentsFor($stage)->isNotEmpty()) {
                return true;
            }
        }

        return false;
    }
}
