<?php

namespace App\Policies;

use App\Models\PayrollPeriod;
use App\Models\User;

class PayrollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewPayroll();
    }

    public function view(User $user, PayrollPeriod $period): bool
    {
        return $user->canViewPayroll();
    }

    public function create(User $user): bool
    {
        return $user->canManagePayroll();
    }

    public function update(User $user, PayrollPeriod $period): bool
    {
        return $user->canManagePayroll() && ! $period->isLocked();
    }

    public function compute(User $user, PayrollPeriod $period): bool
    {
        return $user->canManagePayroll() && $period->status?->allowsRecomputation();
    }

    public function manageSettings(User $user): bool
    {
        return $user->canManagePayroll();
    }

    public function decideOvertime(User $user): bool
    {
        return $user->canManagePayroll();
    }
}
