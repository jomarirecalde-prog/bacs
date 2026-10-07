<?php

namespace App\Policies;

use App\Models\Designation;
use App\Models\User;

class DesignationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewPayroll();
    }

    public function view(User $user, Designation $designation): bool
    {
        return $user->canViewPayroll();
    }

    public function create(User $user): bool
    {
        return $user->canManagePayroll();
    }

    public function update(User $user, Designation $designation): bool
    {
        return $user->canManagePayroll();
    }
}
