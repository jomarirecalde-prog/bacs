<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function view(User $user, Attendance $attendance): bool
    {
        if ($user->isManagement()) {
            return true;
        }

        return $user->employee?->id === $attendance->employee_id;
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return $user->canEditDtr();
    }

    public function create(User $user): bool
    {
        return $user->canEditDtr();
    }
}
