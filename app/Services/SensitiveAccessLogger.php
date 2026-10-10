<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use Illuminate\Http\Request;

class SensitiveAccessLogger
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function fileDownload(
        ?User $user,
        string $module,
        ?int $recordId,
        string $description,
        ?Request $request = null,
        ?array $metadata = null,
    ): void {
        $this->audit->log(
            $user,
            'file_download',
            $module,
            $recordId,
            $description,
            $request,
            $metadata,
        );
    }

    public function payrollRegisterExport(User $user, PayrollPeriod $period, string $format, ?Request $request = null): void
    {
        $this->audit->log(
            $user,
            'payroll_register_export',
            'Payroll',
            $period->id,
            "Payroll register exported ({$format}) for {$period->period_name}.",
            $request,
            ['format' => $format, 'period_id' => $period->id],
        );
    }

    public function employeeSalaryView(
        User $user,
        Employee $employee,
        string $context,
        ?Request $request = null,
        ?array $metadata = null,
    ): void {
        $this->audit->log(
            $user,
            'employee_salary_view',
            'Payroll',
            $employee->id,
            'Employee salary module accessed ('.$context.').',
            $request,
            array_merge(['employee_id' => $employee->id, 'context' => $context], $metadata ?? []),
        );
    }

    public function payslipDownload(User $user, PayrollEmployee $payrollEmployee, ?Request $request = null): void
    {
        $payrollEmployee->loadMissing('employee', 'payrollPeriod');

        $this->audit->log(
            $user,
            'payslip_download',
            'Payroll',
            $payrollEmployee->id,
            'Payslip downloaded for '.($payrollEmployee->employee?->fullName() ?? 'employee')
                .' ('.($payrollEmployee->payrollPeriod?->period_name ?? 'period').').',
            $request,
            [
                'payroll_employee_id' => $payrollEmployee->id,
                'employee_id' => $payrollEmployee->employee_id,
                'payroll_period_id' => $payrollEmployee->payroll_period_id,
            ],
        );
    }
}
