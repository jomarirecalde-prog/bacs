<?php

namespace App\Policies;

use App\Models\LeaveApplication;
use App\Models\User;

class LeaveApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement() || $user->employee !== null || $this->isApprover($user);
    }

    public function view(User $user, LeaveApplication $application): bool
    {
        if ($user->isManagement()) {
            return true;
        }

        if ($user->employee?->id === $application->employee_id) {
            return true;
        }

        return $application->assignments()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    public function update(User $user, LeaveApplication $application): bool
    {
        return $user->employee?->id === $application->employee_id && $application->canBeEdited();
    }

    public function cancel(User $user, LeaveApplication $application): bool
    {
        if (! $application->canBeCancelled()) {
            return false;
        }

        return $user->employee?->id === $application->employee_id || $user->isAdmin();
    }

    public function approve(User $user, LeaveApplication $application): bool
    {
        return app(\App\Services\LeaveApplicationService::class)->userCanAct($user, $application);
    }

    public function processHr(User $user, LeaveApplication $application): bool
    {
        return app(\App\Services\LeaveApplicationService::class)->userCanProcessHr($user, $application);
    }

    public function viewAll(User $user): bool
    {
        return $user->isManagement();
    }

    public function configure(User $user): bool
    {
        return $user->isAdmin();
    }

    public function download(User $user, LeaveApplication $application): bool
    {
        return $this->view($user, $application);
    }

    private function isApprover(User $user): bool
    {
        return app(\App\Services\LeaveApplicationService::class)->userIsAssignedApprover($user);
    }
}
