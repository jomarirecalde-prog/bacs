<?php

namespace App\Policies;

use App\Models\AttendanceCorrectionRequest;
use App\Models\User;

class AttendanceCorrectionRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->employee || $user->isManagement();
    }

    public function view(User $user, AttendanceCorrectionRequest $request): bool
    {
        if ($user->isManagement()) {
            return true;
        }

        return $user->employee?->id === $request->employee_id;
    }

    public function create(User $user): bool
    {
        return (bool) $user->employee;
    }

    public function cancel(User $user, AttendanceCorrectionRequest $request): bool
    {
        return $user->employee?->id === $request->employee_id
            && $request->status?->isOpen();
    }

    public function review(User $user): bool
    {
        return $user->canEditDtr();
    }
}
