<?php

namespace App\Http\Controllers\Employee;

use App\Enums\PayrollPeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\PayrollEmployee;
use App\Services\Payroll\PayslipPdfService;
use App\Services\SensitiveAccessLogger;
use Illuminate\Http\Request;

class PayrollController extends Controller
{
    public function __construct(
        private readonly PayslipPdfService $payslip,
        private readonly SensitiveAccessLogger $sensitiveAccess,
    ) {}

    public function index(Request $request)
    {
        $employee = $this->employee($request);

        $records = PayrollEmployee::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollPeriod', fn ($q) => $q->whereIn('status', [
                PayrollPeriodStatus::Finalized->value,
                PayrollPeriodStatus::Paid->value,
            ]))
            ->with('payrollPeriod')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_employees.payroll_period_id')
            ->orderByDesc('payroll_periods.end_date')
            ->select('payroll_employees.*')
            ->paginate(12);

        return view('employee.payroll.index', compact('records', 'employee'));
    }

    public function show(Request $request, PayrollEmployee $payrollEmployee)
    {
        $this->authorize('view', $payrollEmployee);
        abort_unless($payrollEmployee->employee_id === $this->employee($request)->id, 403);

        $payrollEmployee->load(['payrollPeriod', 'earnings', 'deductions']);

        return view('employee.payroll.show', compact('payrollEmployee'));
    }

    public function pdf(Request $request, PayrollEmployee $payrollEmployee)
    {
        $this->authorize('downloadPayslip', $payrollEmployee);
        abort_unless($payrollEmployee->employee_id === $this->employee($request)->id, 403);
        $this->sensitiveAccess->payslipDownload($request->user(), $payrollEmployee, $request);

        return $this->payslip->download($payrollEmployee);
    }

    public function print(Request $request, PayrollEmployee $payrollEmployee)
    {
        $this->authorize('downloadPayslip', $payrollEmployee);
        abort_unless($payrollEmployee->employee_id === $this->employee($request)->id, 403);

        $data = $this->payslip->viewData($payrollEmployee);

        return view('payroll.print', $data + [
            'pdfUrl' => route('employee.payroll.pdf', $payrollEmployee),
        ]);
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        return $employee;
    }
}
