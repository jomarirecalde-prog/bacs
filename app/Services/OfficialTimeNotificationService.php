<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Models\OfficialTimeApprovalAssignment;
use App\Models\OfficialTimeRequest;
use App\Models\User;

class OfficialTimeNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function submitted(OfficialTimeRequest $request): void
    {
        $user = $request->employee?->user;
        if ($user) {
            $this->notify(
                $user,
                $request,
                'Official Time submitted',
                "Official Time {$request->request_no} was submitted and is awaiting endorsement.",
                'success',
                'submitted'
            );
        }

        $this->notifyStageApprovers($request, LeaveApprovalStage::ImmediateSupervisor, true);
    }

    public function stageReady(OfficialTimeRequest $request, LeaveApprovalStage $stage): void
    {
        $titles = [
            LeaveApprovalStage::CeoFinalApproval->value => 'Official Time awaiting final approval',
            LeaveApprovalStage::ImmediateSupervisor->value => 'New Official Time for endorsement',
        ];

        $this->notifyStageApprovers(
            $request,
            $stage,
            true,
            $titles[$stage->value] ?? 'Official Time requires your action'
        );
    }

    public function decisionToRequester(OfficialTimeRequest $request, string $title, string $message, string $type = 'info'): void
    {
        $user = $request->employee?->user;
        if (! $user) {
            return;
        }

        $this->notify($user, $request, $title, $message, $type, 'status-'.$request->status->value);
    }

    public function approved(OfficialTimeRequest $request): void
    {
        $this->decisionToRequester(
            $request,
            'Official Time approved',
            "Official Time {$request->request_no} has been fully approved.",
            'success'
        );
    }

    public function returned(OfficialTimeRequest $request, string $reason): void
    {
        $this->decisionToRequester(
            $request,
            'Official Time returned for revision',
            "Official Time {$request->request_no} was returned for revision. Notes: {$reason}",
            'warning'
        );
    }

    public function cancelled(OfficialTimeRequest $request): void
    {
        $pending = $request->assignments()->where('status', 'pending')->with('user')->get();

        foreach ($pending as $assignment) {
            if ($assignment->user) {
                $this->notify(
                    $assignment->user,
                    $request,
                    'Official Time cancelled',
                    "{$request->employee?->fullName()} cancelled {$request->request_no}.",
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
                'Official Time cancelled',
                "Official Time {$request->request_no} was cancelled.",
                'warning',
                'cancelled'
            );
        }
    }

    private function notifyStageApprovers(OfficialTimeRequest $request, LeaveApprovalStage $stage, bool $pendingOnly = true, ?string $title = null): void
    {
        $query = $request->assignments()->where('stage', $stage->value)->with('user');
        if ($pendingOnly) {
            $query->where('status', 'pending');
        }

        $employeeName = $request->employee?->fullName() ?? 'An employee';
        $title ??= 'New Official Time for endorsement';
        $message = "{$employeeName} submitted Official Time {$request->request_no} for {$request->date?->format('M j, Y')}.";

        foreach ($query->get() as $assignment) {
            /** @var OfficialTimeApprovalAssignment $assignment */
            if (! $assignment->user) {
                continue;
            }

            $this->notify($assignment->user, $request, $title, $message, 'warning', 'stage-'.$stage->value);
        }
    }

    private function notify(User $user, OfficialTimeRequest $request, string $title, string $message, string $type, string $action): void
    {
        $this->notifications->notify(
            $user,
            $title,
            $message,
            $type,
            $this->linkFor($user, $request),
            action: $action,
            officialTimeRequestId: $request->id,
        );
    }

    private function linkFor(User $user, OfficialTimeRequest $request): string
    {
        if ($user->employee?->id === $request->employee_id) {
            return route('employee.official-time.show', $request);
        }

        if ($user->isAdmin() || $user->isManagement()) {
            return route('admin.official-time.show', $request);
        }

        return route('official-time.approvals.show', $request);
    }
}
