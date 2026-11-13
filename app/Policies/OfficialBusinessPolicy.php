<?php

namespace App\Policies;

use App\Models\OfficialBusinessRequest;
use App\Models\User;
use App\Services\OfficialBusinessService;

class OfficialBusinessPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement() || $user->employee !== null || $this->isApprover($user);
    }

    public function view(User $user, OfficialBusinessRequest $request): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isManagement()) {
            return true;
        }

        if ($user->employee?->id === $request->employee_id) {
            return true;
        }

        if ($request->participants()->where('employee_id', $user->employee?->id)->exists()) {
            return true;
        }

        return $request->assignments()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    public function update(User $user, OfficialBusinessRequest $request): bool
    {
        return $user->employee?->id === $request->employee_id && $request->canBeEditedByEmployee();
    }

    public function cancel(User $user, OfficialBusinessRequest $request): bool
    {
        if (! $request->canBeCancelledByEmployee() && ! $user->isAdmin()) {
            return false;
        }

        return $user->employee?->id === $request->employee_id || $user->isAdmin();
    }

    public function endorse(User $user, OfficialBusinessRequest $request): bool
    {
        return app(OfficialBusinessService::class)->userCanAct($user, $request);
    }

    public function viewAll(User $user): bool
    {
        return $user->isAdmin();
    }

    public function download(User $user, OfficialBusinessRequest $request): bool
    {
        if (! $request->canDownloadPdf()) {
            return false;
        }

        return $this->view($user, $request);
    }

    private function isApprover(User $user): bool
    {
        return app(OfficialBusinessService::class)->userIsAssignedApprover($user)
            || \App\Models\LeaveApprovalWorkflowApprover::query()->where('user_id', $user->id)->exists();
    }
}
