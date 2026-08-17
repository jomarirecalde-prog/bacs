<?php

namespace App\Policies;

use App\Models\AttendanceStation;
use App\Models\User;

class AttendanceStationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, AttendanceStation $station): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, AttendanceStation $station): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, AttendanceStation $station): bool
    {
        return $user->isAdmin();
    }

    public function manageDevice(User $user, AttendanceStation $station): bool
    {
        return $user->isAdmin();
    }
}
