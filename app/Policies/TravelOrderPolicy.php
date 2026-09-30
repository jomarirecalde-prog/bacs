<?php

namespace App\Policies;

use App\Enums\TravelOrderStatus;
use App\Models\TravelOrder;
use App\Models\User;
use App\Services\TravelOrderService;

class TravelOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement() || $user->employee !== null || $this->isApprover($user);
    }

    public function view(User $user, TravelOrder $order): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManagement()) {
            return true;
        }

        if ($user->employee?->id === $order->requester_id) {
            return true;
        }

        if ($order->personnel()->where('employee_id', $user->employee?->id)->exists()) {
            return true;
        }

        return $order->assignments()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    public function update(User $user, TravelOrder $order): bool
    {
        return $user->employee?->id === $order->requester_id && $order->canBeEditedByRequester();
    }

    public function cancel(User $user, TravelOrder $order): bool
    {
        if (! $order->canBeCancelledByRequester() && ! $user->isAdmin()) {
            return false;
        }

        return $user->employee?->id === $order->requester_id || $user->isAdmin();
    }

    public function endorse(User $user, TravelOrder $order): bool
    {
        return app(TravelOrderService::class)->userCanAct($user, $order);
    }

    public function viewAll(User $user): bool
    {
        return $user->isAdmin();
    }

    public function adminEditApproved(User $user, TravelOrder $order): bool
    {
        return $user->isAdmin() && $order->status === TravelOrderStatus::Approved;
    }

    public function adminCancelApproved(User $user, TravelOrder $order): bool
    {
        return $user->isAdmin() && $order->status === TravelOrderStatus::Approved;
    }

    public function viewModificationHistory(User $user): bool
    {
        return $user->isAdmin();
    }

    public function download(User $user, TravelOrder $order): bool
    {
        if (! $order->canDownloadPdf()) {
            return false;
        }

        return $this->view($user, $order);
    }

    private function isApprover(User $user): bool
    {
        return app(TravelOrderService::class)->userIsAssignedApprover($user)
            || \App\Models\LeaveApprovalWorkflowApprover::query()->where('user_id', $user->id)->exists();
    }
}
