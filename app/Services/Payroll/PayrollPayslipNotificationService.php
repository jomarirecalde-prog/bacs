<?php

namespace App\Services\Payroll;

use App\Mail\PayslipAvailableMail;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PayrollPayslipNotificationService
{
    public function notifyPeriod(PayrollPeriod $period, User $actor): int
    {
        $sent = 0;

        $period->load(['payrollEmployees.employee.user']);

        foreach ($period->payrollEmployees as $row) {
            $email = $row->employee?->email ?? $row->employee?->user?->email;
            if (! $email) {
                continue;
            }

            Mail::to($email)->send(new PayslipAvailableMail($row, $period));
            $sent++;
        }

        return $sent;
    }
}
