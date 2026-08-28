<?php

namespace App\Policies;

use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isManagement();
    }

    public function view(User $user, Employee $employee): bool
    {
        if ($user->isManagement()) {
            return true;
        }

        return $user->employee?->id === $employee->id;
    }

    public function create(User $user): bool
    {
        return $user->canManageEmployees();
    }

    public function update(User $user, Employee $employee): bool
    {
        return $user->canManageEmployees();
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $user->isAdmin();
    }

    public function viewLeaveAdjustments(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee);
    }

    public function viewLeaveHistory(User $user, Employee $employee): bool
    {
        return $this->view($user, $employee);
    }
}
