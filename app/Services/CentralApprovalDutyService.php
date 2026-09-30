<?php

namespace App\Services;

use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveStatus;
use App\Enums\TravelOrderStatus;
use App\Models\ApprovalWorkflowAssignee;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\AttendanceCorrectionApprovalAssignment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalAssignment;
use App\Models\TravelOrder;
use App\Models\TravelOrderApprovalAssignment;
use App\Models\User;

class CentralApprovalDutyService
{
    /** @return list<array{route_name: string, params: array<string, string>, label: string, count: int}> */
    public function sidebarModules(User $user): array
    {
        $employeeId = $user->employee?->id;
        if (! $employeeId) {
            return [];
        }

        $modules = [];

        if ($this->isConfiguredEndorser($employeeId, ApprovalTransactionType::LeaveApplication)) {
            $modules[] = $this->module(
                'Leave Application Endorsement',
                'leave.approvals.index',
                ['scope' => 'endorsement'],
                $this->countLeaveEndorsement($user)
            );
        }

        if ($this->isConfiguredFinalApprover($employeeId, ApprovalTransactionType::LeaveApplication)) {
            $modules[] = $this->module(
                'Leave Application Final Approval',
                'leave.approvals.index',
                ['scope' => 'final'],
                $this->countLeaveFinal($user)
            );
        }

        if ($this->isConfiguredEndorser($employeeId, ApprovalTransactionType::Pardon)) {
            $modules[] = $this->module(
                'Pardon / Time Correction Endorsement',
                'pardon.approvals.index',
                ['scope' => 'endorsement'],
                $this->countPardonEndorsement($user)
            );
        }

        if ($this->isConfiguredFinalApprover($employeeId, ApprovalTransactionType::Pardon)) {
            $modules[] = $this->module(
                'Pardon / Time Correction Final Approval',
                'pardon.approvals.index',
                ['scope' => 'final'],
                $this->countPardonFinal($user)
            );
        }

        if ($this->isConfiguredEndorser($employeeId, ApprovalTransactionType::TravelOrder)) {
            $modules[] = $this->module(
                'Travel Order Endorsement',
                'travel-order.approvals.index',
                ['scope' => 'endorsement'],
                $this->countTravelEndorsement($user)
            );
        }

        if ($this->isConfiguredFinalApprover($employeeId, ApprovalTransactionType::TravelOrder)) {
            $modules[] = $this->module(
                'Travel Order Final Approval',
                'travel-order.approvals.index',
                ['scope' => 'final'],
                $this->countTravelFinal($user)
            );
        }

        return $modules;
    }

    public function hasAnyConfiguredDuty(User $user): bool
    {
        return $this->sidebarModules($user) !== [];
    }

    private function isConfiguredEndorser(int $employeeId, ApprovalTransactionType $type): bool
    {
        $configId = ApprovalWorkflowConfiguration::query()->where('transaction_type', $type->value)->value('id');

        return ApprovalWorkflowAssignee::query()
            ->where('workflow_configuration_id', $configId)
            ->where('employee_id', $employeeId)
            ->where('approval_type', ApprovalAssigneeType::Endorser->value)
            ->where('is_active', true)
            ->exists();
    }

    private function isConfiguredFinalApprover(int $employeeId, ApprovalTransactionType $type): bool
    {
        $configId = ApprovalWorkflowConfiguration::query()->where('transaction_type', $type->value)->value('id');

        return ApprovalWorkflowAssignee::query()
            ->where('workflow_configuration_id', $configId)
            ->where('employee_id', $employeeId)
            ->where('approval_type', ApprovalAssigneeType::FinalApprover->value)
            ->where('is_active', true)
            ->exists();
    }

    private function countLeaveEndorsement(User $user): int
    {
        return LeaveApplication::query()
            ->where('status', LeaveStatus::PendingSupervisor)
            ->where('current_stage', LeaveApprovalStage::ImmediateSupervisor)
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('stage', LeaveApprovalStage::ImmediateSupervisor->value)->where('status', 'pending'))
            ->count();
    }

    private function countLeaveFinal(User $user): int
    {
        return LeaveApplication::query()
            ->where('status', LeaveStatus::PendingCeoFinalApproval)
            ->where('current_stage', LeaveApprovalStage::CeoFinalApproval)
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('stage', LeaveApprovalStage::CeoFinalApproval->value)->where('status', 'pending'))
            ->count();
    }

    private function countTravelEndorsement(User $user): int
    {
        return TravelOrder::query()
            ->where('status', TravelOrderStatus::PendingSupervisor)
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('stage', LeaveApprovalStage::ImmediateSupervisor->value)->where('status', 'pending'))
            ->count();
    }

    private function countTravelFinal(User $user): int
    {
        return TravelOrder::query()
            ->where('status', TravelOrderStatus::PendingCeoFinalApproval)
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('stage', LeaveApprovalStage::CeoFinalApproval->value)->where('status', 'pending'))
            ->count();
    }

    private function countPardonEndorsement(User $user): int
    {
        return AttendanceCorrectionRequest::query()
            ->where('status', AttendanceCorrectionStatus::PendingEndorsement)
            ->whereHas('approvalAssignments', fn ($q) => $q->where('user_id', $user->id)->where('stage', LeaveApprovalStage::ImmediateSupervisor->value)->where('status', 'pending'))
            ->count();
    }

    private function countPardonFinal(User $user): int
    {
        return AttendanceCorrectionRequest::query()
            ->where('status', AttendanceCorrectionStatus::PendingFinalApproval)
            ->whereHas('approvalAssignments', fn ($q) => $q->where('user_id', $user->id)->where('stage', LeaveApprovalStage::CeoFinalApproval->value)->where('status', 'pending'))
            ->count();
    }

    /** @param  array<string, string>  $params
     * @return array{route_name: string, params: array<string, string>, label: string, count: int}
     */
    private function module(string $label, string $routeName, array $params, int $count): array
    {
        return [
            'label' => $label,
            'route_name' => $routeName,
            'params' => $params,
            'count' => $count,
        ];
    }
}
