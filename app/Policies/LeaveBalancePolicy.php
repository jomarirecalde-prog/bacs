<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\LeaveBalanceAdjustment;
use App\Models\User;
use App\Services\LeaveApplicationService;

class LeaveBalancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->employee?->id === $employee->id;
    }

    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    public function adjust(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewAdjustments(User $user, Employee $employee): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->employee?->id === $employee->id;
    }

    public function viewLeaveHistory(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee);
    }

    public function configurePolicy(User $user): bool
    {
        return $user->isAdmin();
    }

    public function viewAdjustmentRecord(User $user, LeaveBalanceAdjustment $adjustment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->employee?->id === $adjustment->employee_id;
    }

    public function isHrPersonnel(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return app(LeaveApplicationService::class)->userIsAssignedApprover($user);
    }
}
