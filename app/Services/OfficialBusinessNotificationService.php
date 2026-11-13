<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Models\OfficialBusinessApprovalAssignment;
use App\Models\OfficialBusinessRequest;
use App\Models\User;

class OfficialBusinessNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function submitted(OfficialBusinessRequest $request): void
    {
        $user = $request->employee?->user;
        if ($user) {
            $this->notify(
                $user,
                $request,
                'Official business submitted',
                "Official business {$request->reference_number} was submitted and is awaiting endorsement.",
                'success',
                'submitted'
            );
        }

        $this->notifyStageApprovers($request, LeaveApprovalStage::ImmediateSupervisor, true);
    }

    public function stageReady(OfficialBusinessRequest $request, LeaveApprovalStage $stage): void
    {
        $titles = [
            LeaveApprovalStage::DepartmentHead->value => 'Official business ready for department head',
            LeaveApprovalStage::AdministrativeHead->value => 'Official business ready for administrative head',
            LeaveApprovalStage::CeoFinalApproval->value => 'Official business awaiting final approval',
            LeaveApprovalStage::ImmediateSupervisor->value => 'New official business for endorsement',
        ];

        $this->notifyStageApprovers(
            $request,
            $stage,
            true,
            $titles[$stage->value] ?? 'Official business requires your action'
        );
    }

    public function decisionToRequester(OfficialBusinessRequest $request, string $title, string $message, string $type = 'info'): void
    {
        $user = $request->employee?->user;
        if (! $user) {
            return;
        }

        $this->notify($user, $request, $title, $message, $type, 'status-'.$request->status->value);
    }

    public function approved(OfficialBusinessRequest $request): void
    {
        $this->decisionToRequester(
            $request,
            'Official business approved',
            "Official business {$request->reference_number} has been fully approved.",
            'success'
        );

        foreach ($request->participants as $participant) {
            $participantUser = $participant->employee?->user;
            if ($participantUser) {
                $this->notify(
                    $participantUser,
                    $request,
                    'Official business approved',
                    "You are listed on approved official business {$request->reference_number}.",
                    'success',
                    'participant-approved'
                );
            }
        }
    }

    public function cancelled(OfficialBusinessRequest $request): void
    {
        $pending = $request->assignments()->where('status', 'pending')->with('user')->get();

        foreach ($pending as $assignment) {
            if ($assignment->user) {
                $this->notify(
                    $assignment->user,
                    $request,
                    'Official business cancelled',
                    "{$request->employee?->fullName()} cancelled {$request->reference_number}.",
                    'warning',
                    'cancelled-approver'
                );
            }
        }

        $user = $request->employee?->user;
        if ($user) {
            $this->notify(
                $user,
                $request,
                'Official business cancelled',
                "Official business {$request->reference_number} was cancelled.",
                'warning',
                'cancelled'
            );
        }
    }

    private function notifyStageApprovers(OfficialBusinessRequest $request, LeaveApprovalStage $stage, bool $pendingOnly = true, ?string $title = null): void
    {
        $query = $request->assignments()->where('stage', $stage->value)->with('user');
        if ($pendingOnly) {
            $query->where('status', 'pending');
        }

        $requesterName = $request->employee?->fullName() ?? 'An employee';
        $title ??= 'New official business for endorsement';
        $message = "{$requesterName} submitted official business {$request->reference_number}.";

        foreach ($query->get() as $assignment) {
            /** @var OfficialBusinessApprovalAssignment $assignment */
            if (! $assignment->user) {
                continue;
            }

            $this->notify($assignment->user, $request, $title, $message, 'warning', 'stage-'.$stage->value);
        }
    }

    private function notify(User $user, OfficialBusinessRequest $request, string $title, string $message, string $type, string $action): void
    {
        $this->notifications->notify(
            $user,
            $title,
            $message,
            $type,
            $this->linkFor($user, $request),
            action: $action,
        );
    }

    private function linkFor(User $user, OfficialBusinessRequest $request): string
    {
        if ($user->employee?->id === $request->employee_id) {
            return route('employee.official-business.show', $request);
        }

        if ($user->isAdmin()) {
            return route('admin.official-business.show', $request);
        }

        if ($request->participants()->where('employee_id', $user->employee?->id)->exists()) {
            return route('employee.official-business.show', $request);
        }

        return route('official-business.approvals.show', $request);
    }
}
