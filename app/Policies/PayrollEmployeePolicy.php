<?php

namespace App\Policies;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollEmployee;
use App\Models\User;

class PayrollEmployeePolicy
{
    public function view(User $user, PayrollEmployee $payrollEmployee): bool
    {
        if ($user->canViewPayroll()) {
            return true;
        }

        return $this->employeeOwnsReleasedPayslip($user, $payrollEmployee);
    }

    public function downloadPayslip(User $user, PayrollEmployee $payrollEmployee): bool
    {
        return $this->view($user, $payrollEmployee);
    }

    public function update(User $user, PayrollEmployee $payrollEmployee): bool
    {
        if (! $user->canManagePayroll()) {
            return false;
        }

        $period = $payrollEmployee->payrollPeriod;

        return $period && ! $period->isLocked();
    }

    private function employeeOwnsReleasedPayslip(User $user, PayrollEmployee $payrollEmployee): bool
    {
        if ($user->employee?->id !== $payrollEmployee->employee_id) {
            return false;
        }

        $period = $payrollEmployee->payrollPeriod;
        if (! $period) {
            return false;
        }

        return in_array($period->status, [
            PayrollPeriodStatus::Finalized,
            PayrollPeriodStatus::Paid,
        ], true);
    }
}
