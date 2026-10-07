<?php

namespace App\Policies;

use App\Models\OfficialTimeRequest;
use App\Models\User;
use App\Services\OfficialTimeService;

class OfficialTimeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement() || $user->employee !== null || $this->isApprover($user);
    }

    public function view(User $user, OfficialTimeRequest $request): bool
    {
        if ($user->isAdmin() || $user->isManagement()) {
            return true;
        }

        if ($user->employee?->id === $request->employee_id) {
            return true;
        }

        return $request->assignments()->where('user_id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    public function update(User $user, OfficialTimeRequest $request): bool
    {
        return $user->employee?->id === $request->employee_id && $request->canBeEditedByEmployee();
    }

    public function cancel(User $user, OfficialTimeRequest $request): bool
    {
        if (! $request->canBeCancelledByEmployee() && ! $user->isAdmin()) {
            return false;
        }

        return $user->employee?->id === $request->employee_id || $user->isAdmin();
    }

    public function endorse(User $user, OfficialTimeRequest $request): bool
    {
        return app(OfficialTimeService::class)->userCanAct($user, $request);
    }

    public function downloadAttachment(User $user, OfficialTimeRequest $request): bool
    {
        return $this->view($user, $request) && filled($request->attachment_path);
    }

    public function viewAll(User $user): bool
    {
        return $user->isAdmin() || $user->isManagement();
    }

    private function isApprover(User $user): bool
    {
        return app(OfficialTimeService::class)->userIsAssignedApprover($user);
    }
}
