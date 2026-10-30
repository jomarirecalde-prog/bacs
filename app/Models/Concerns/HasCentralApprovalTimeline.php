<?php

namespace App\Models\Concerns;

use App\Enums\EndorsementMode;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveParallelRule;

trait HasCentralApprovalTimeline
{
    public function usesCentralApprovalWorkflow(): bool
    {
        return filled($this->central_approval_config_id);
    }

    /** @return list<LeaveApprovalStage> */
    public function approvalTimelineStages(): array
    {
        if ($this->usesCentralApprovalWorkflow()) {
            return $this->centralApprovalTimelineStages();
        }

        if (! $this->usesLegacyDepartmentApprovalStages()) {
            return $this->centralApprovalTimelineStages();
        }

        return LeaveApprovalStage::sequence();
    }

    public function approvalTimelineUsesCentralLabels(): bool
    {
        if ($this->usesCentralApprovalWorkflow()) {
            return true;
        }

        return ! $this->usesLegacyDepartmentApprovalStages();
    }

    public function snapshotEndorsementMode(): EndorsementMode
    {
        $value = $this->getAttribute('endorsement_mode');
        if ($value instanceof EndorsementMode) {
            return $value;
        }

        return EndorsementMode::tryFrom((string) $value) ?? EndorsementMode::Parallel;
    }

    public function snapshotParallelRule(): LeaveParallelRule
    {
        $value = $this->getAttribute('parallel_rule') ?? $this->getAttribute('endorsement_parallel_rule');
        if ($value instanceof LeaveParallelRule) {
            return $value;
        }

        return LeaveParallelRule::tryFrom((string) $value) ?? LeaveParallelRule::All;
    }

    /** @return list<LeaveApprovalStage> */
    public function activeApprovalStageSequence(): array
    {
        if ($this->usesCentralApprovalWorkflow()) {
            return $this->centralApprovalTimelineStages();
        }

        return LeaveApprovalStage::sequence();
    }

    public function firstActiveApprovalStage(): ?LeaveApprovalStage
    {
        return $this->nextApprovalStageAfter(null);
    }

    public function nextApprovalStageAfter(?LeaveApprovalStage $from): ?LeaveApprovalStage
    {
        $stages = $this->activeApprovalStageSequence();
        if ($from === null) {
            foreach ($stages as $stage) {
                if ($this->assignmentsFor($stage)->isNotEmpty()) {
                    return $stage;
                }
            }

            return null;
        }

        $passed = false;
        foreach ($stages as $stage) {
            if ($passed && $this->assignmentsFor($stage)->isNotEmpty()) {
                return $stage;
            }
            if ($stage === $from) {
                $passed = true;
            }
        }

        return null;
    }

    /** @return list<LeaveApprovalStage> */
    protected function centralApprovalTimelineStages(): array
    {
        $ordered = [
            LeaveApprovalStage::ImmediateSupervisor,
            LeaveApprovalStage::DepartmentHead,
            LeaveApprovalStage::CeoFinalApproval,
        ];

        $stages = array_values(array_filter(
            $ordered,
            fn (LeaveApprovalStage $stage) => $this->assignmentsFor($stage)->isNotEmpty()
        ));

        if ($this->includesHrApprovalStage()) {
            $stages[] = LeaveApprovalStage::HrOfficer;

            return $stages;
        }

        if ($stages !== []) {
            return $stages;
        }

        $hr = $this->assignmentsFor(LeaveApprovalStage::HrOfficer);
        if ($hr->isNotEmpty()) {
            return [LeaveApprovalStage::HrOfficer];
        }

        return [];
    }

    protected function includesHrApprovalStage(): bool
    {
        return false;
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
