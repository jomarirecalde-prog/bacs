<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\PayrollEmployee;
use App\Services\AuditLogger;
use App\Services\Payroll\EmployeeSalaryService;
use App\Services\Payroll\PayrollEngine;
use App\Services\SensitiveAccessLogger;
use App\Services\Payroll\PayslipPdfService;
use Illuminate\Http\Request;

class PayrollEmployeeController extends Controller
{
    public function __construct(
        private readonly PayslipPdfService $payslip,
        private readonly PayrollEngine $payroll,
        private readonly AuditLogger $audit,
        private readonly SensitiveAccessLogger $sensitiveAccess,
        private readonly EmployeeSalaryService $salaries,
    ) {}

    public function show(PayrollEmployee $payrollEmployee)
    {
        $this->authorize('view', $payrollEmployee);

        $payrollEmployee->load(['payrollPeriod', 'earnings', 'deductions', 'employee']);

        $periodEnd = $payrollEmployee->payrollPeriod?->end_date?->toDateString();
        $salaryRecord = $periodEnd && $payrollEmployee->employee
            ? $this->salaries->effectiveForDate($payrollEmployee->employee, $periodEnd)
            : null;
        $declaredGrossPerCutoff = $salaryRecord
            ? $this->salaries->grossCompensationPerCutoff($salaryRecord, (float) $payrollEmployee->de_minimis)
            : null;

        return view('admin.payroll.employees.show', compact('payrollEmployee', 'declaredGrossPerCutoff'));
    }

    public function updateTotalBasicPay(Request $request, PayrollEmployee $payrollEmployee)
    {
        $this->authorize('update', $payrollEmployee);

        $data = $request->validate([
            'total_basic_pay' => ['required', 'numeric', 'min:0'],
        ]);

        $updated = $this->payroll->applyManualTotalBasicPay(
            $payrollEmployee,
            (float) $data['total_basic_pay'],
            $request->user(),
        );

        $this->audit->log(
            $request->user(),
            'payroll_total_basic_pay_set',
            'Payroll',
            $updated->id,
            "Total basic pay set to {$data['total_basic_pay']} for {$updated->employee_name}.",
            $request,
            ['total_basic_pay' => (float) $data['total_basic_pay']],
        );

        return redirect()
            ->route('admin.payroll.employees.payroll.show', $updated)
            ->with('success', 'Total basic pay saved. Gross pay and net pay were recalculated.');
    }

    public function pdf(Request $request, PayrollEmployee $payrollEmployee)
    {
        $this->authorize('downloadPayslip', $payrollEmployee);
        $this->sensitiveAccess->payslipDownload($request->user(), $payrollEmployee, $request);

        return $this->payslip->download($payrollEmployee);
    }

    public function print(PayrollEmployee $payrollEmployee)
    {
        $this->authorize('downloadPayslip', $payrollEmployee);

        return view('payroll.print', $this->payslip->viewData($payrollEmployee) + [
            'pdfUrl' => route('admin.payroll.employees.payroll.pdf', $payrollEmployee),
        ]);
    }
}
