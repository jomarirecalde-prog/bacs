<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveStatus;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalAssignment;
use App\Models\User;

class LeaveNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function submitted(LeaveApplication $application): void
    {
        $employee = $application->employee;
        $user = $employee?->user;
        if ($user) {
            $this->notify(
                $user,
                $application,
                'Leave application submitted',
                "Your leave application {$application->application_number} was submitted and is awaiting approval.",
                'success',
                'submitted'
            );
        }

        $this->notifyStageApprovers($application, LeaveApprovalStage::ImmediateSupervisor, true);
    }

    public function stageReady(LeaveApplication $application, LeaveApprovalStage $stage): void
    {
        $titles = [
            LeaveApprovalStage::DepartmentHead->value => 'Leave request ready for department head',
            LeaveApprovalStage::AdministrativeHead->value => 'Leave request ready for administrative head',
            LeaveApprovalStage::HrOfficer->value => 'Leave request ready for HR processing',
            LeaveApprovalStage::ImmediateSupervisor->value => 'New leave application for approval',
        ];

        $this->notifyStageApprovers(
            $application,
            $stage,
            true,
            $titles[$stage->value] ?? 'Leave request requires your action'
        );
    }

    public function decisionToEmployee(LeaveApplication $application, string $title, string $message, string $type = 'info'): void
    {
        $user = $application->employee?->user;
        if (! $user) {
            return;
        }

        $this->notify($user, $application, $title, $message, $type, 'status-'.$application->status->value);
    }

    public function cancelled(LeaveApplication $application): void
    {
        $pending = $application->assignments()->where('status', 'pending')->with('user')->get();

        foreach ($pending as $assignment) {
            if ($assignment->user) {
                $this->notify(
                    $assignment->user,
                    $application,
                    'Leave application cancelled',
                    "{$application->employee?->fullName()} cancelled {$application->application_number}.",
                    'warning',
                    'cancelled-approver'
                );
            }
        }

        $user = $application->employee?->user;
        if ($user) {
            $this->notify(
                $user,
                $application,
                'Leave application cancelled',
                "Your leave application {$application->application_number} was cancelled.",
                'warning',
                'cancelled'
            );
        }
    }

    public function needsEmployeeAction(LeaveApplication $application, string $message): void
    {
        $user = $application->employee?->user;
        if (! $user) {
            return;
        }

        $this->notify($user, $application, 'Leave application requires action', $message, 'warning', 'action-required');
    }

    private function notifyStageApprovers(LeaveApplication $application, LeaveApprovalStage $stage, bool $pendingOnly = true, ?string $title = null): void
    {
        $query = $application->assignments()->where('stage', $stage->value)->with('user');
        if ($pendingOnly) {
            $query->where('status', 'pending');
        }

        $employeeName = $application->employee?->fullName() ?? 'An employee';
        $title ??= 'New leave application for approval';
        $message = "{$employeeName} filed {$application->application_number} ({$application->leaveTypeLabel()}, {$application->requested_days} day(s)).";

        foreach ($query->get() as $assignment) {
            /** @var LeaveApprovalAssignment $assignment */
            if (! $assignment->user) {
                continue;
            }

            $this->notify($assignment->user, $application, $title, $message, 'warning', 'stage-'.$stage->value);
        }
    }

    private function notify(User $user, LeaveApplication $application, string $title, string $message, string $type, string $action): void
    {
        $this->notifications->notify(
            $user,
            $title,
            $message,
            $type,
            $this->linkFor($user, $application),
            action: $action,
            leaveApplicationId: $application->id,
        );
    }

    private function linkFor(User $user, LeaveApplication $application): string
    {
        if ($user->employee?->id === $application->employee_id) {
            return route('employee.leave.show', $application);
        }

        if ($user->isManagement()) {
            return route('admin.leave.show', $application);
        }

        return route('leave.approvals.show', $application);
    }
}
