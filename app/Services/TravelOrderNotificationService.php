<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Models\TravelOrder;
use App\Models\TravelOrderApprovalAssignment;
use App\Models\User;

class TravelOrderNotificationService
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function submitted(TravelOrder $order): void
    {
        $user = $order->requester?->user;
        if ($user) {
            $this->notify(
                $user,
                $order,
                'Travel order submitted',
                "Travel order {$order->travel_order_number} was submitted and is awaiting endorsement.",
                'success',
                'submitted'
            );
        }

        $this->notifyStageApprovers($order, LeaveApprovalStage::ImmediateSupervisor, true);
    }

    public function stageReady(TravelOrder $order, LeaveApprovalStage $stage): void
    {
        $titles = [
            LeaveApprovalStage::DepartmentHead->value => 'Travel order ready for department head',
            LeaveApprovalStage::AdministrativeHead->value => 'Travel order ready for administrative head',
            LeaveApprovalStage::CeoFinalApproval->value => 'Travel order awaiting final approval',
            LeaveApprovalStage::ImmediateSupervisor->value => 'New travel order for endorsement',
        ];

        $this->notifyStageApprovers(
            $order,
            $stage,
            true,
            $titles[$stage->value] ?? 'Travel order requires your action'
        );
    }

    public function decisionToRequester(TravelOrder $order, string $title, string $message, string $type = 'info'): void
    {
        $user = $order->requester?->user;
        if (! $user) {
            return;
        }

        $this->notify($user, $order, $title, $message, $type, 'status-'.$order->status->value);
    }

    public function approved(TravelOrder $order): void
    {
        $this->decisionToRequester(
            $order,
            'Travel order approved',
            "Travel order {$order->travel_order_number} has been fully approved.",
            'success'
        );

        foreach ($order->personnel as $person) {
            $travelerUser = $person->employee?->user;
            if ($travelerUser) {
                $this->notify(
                    $travelerUser,
                    $order,
                    'Travel order approved',
                    "You are listed on approved travel order {$order->travel_order_number}.",
                    'success',
                    'traveler-approved'
                );
            }
        }
    }

    public function cancelled(TravelOrder $order): void
    {
        $pending = $order->assignments()->where('status', 'pending')->with('user')->get();

        foreach ($pending as $assignment) {
            if ($assignment->user) {
                $this->notify(
                    $assignment->user,
                    $order,
                    'Travel order cancelled',
                    "{$order->requester?->fullName()} cancelled {$order->travel_order_number}.",
                    'warning',
                    'cancelled-approver'
                );
            }
        }

        $user = $order->requester?->user;
        if ($user) {
            $this->notify(
                $user,
                $order,
                'Travel order cancelled',
                "Travel order {$order->travel_order_number} was cancelled.",
                'warning',
                'cancelled'
            );
        }
    }

    public function adminUpdated(TravelOrder $order): void
    {
        $title = 'Travel Order Updated';
        $message = "Your approved Travel Order {$order->travel_order_number} has been updated by the Super Admin. Please review the updated travel details.";

        $requester = $order->requester?->user;
        if ($requester) {
            $this->notify($requester, $order, $title, $message, 'warning', 'admin-updated');
        }

        foreach ($order->personnel as $person) {
            $user = $person->employee?->user;
            if ($user) {
                $this->notify($user, $order, $title, $message, 'warning', 'admin-updated-traveler');
            }
        }
    }

    public function adminCancelled(TravelOrder $order, string $reason): void
    {
        $title = 'Travel Order Cancelled';
        $message = "Travel Order {$order->travel_order_number} has been cancelled by the Super Admin. Reason: {$reason}";

        $requester = $order->requester?->user;
        if ($requester) {
            $this->notify($requester, $order, $title, $message, 'error', 'admin-cancelled');
        }

        foreach ($order->personnel as $person) {
            $user = $person->employee?->user;
            if ($user) {
                $this->notify($user, $order, $title, $message, 'error', 'admin-cancelled-traveler');
            }
        }
    }

    private function notifyStageApprovers(TravelOrder $order, LeaveApprovalStage $stage, bool $pendingOnly = true, ?string $title = null): void
    {
        $query = $order->assignments()->where('stage', $stage->value)->with('user');
        if ($pendingOnly) {
            $query->where('status', 'pending');
        }

        $requesterName = $order->requester?->fullName() ?? 'An employee';
        $title ??= 'New travel order for endorsement';
        $message = "{$requesterName} submitted travel order {$order->travel_order_number}.";

        foreach ($query->get() as $assignment) {
            /** @var TravelOrderApprovalAssignment $assignment */
            if (! $assignment->user) {
                continue;
            }

            $this->notify($assignment->user, $order, $title, $message, 'warning', 'stage-'.$stage->value);
        }
    }

    private function notify(User $user, TravelOrder $order, string $title, string $message, string $type, string $action): void
    {
        $this->notifications->notify(
            $user,
            $title,
            $message,
            $type,
            $this->linkFor($user, $order),
            action: $action,
            travelOrderId: $order->id,
        );
    }

    private function linkFor(User $user, TravelOrder $order): string
    {
        if ($user->employee?->id === $order->requester_id) {
            return route('employee.travel-orders.show', $order);
        }

        if ($user->isAdmin()) {
            return route('admin.travel-orders.show', $order);
        }

        if ($order->personnel()->where('employee_id', $user->employee?->id)->exists()) {
            return route('employee.travel-orders.show', $order);
        }

        return route('travel-order.approvals.show', $order);
    }
}
