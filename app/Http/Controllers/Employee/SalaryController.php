<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollEmployee;
use App\Services\Payroll\EmployeeSalaryOverviewService;
use App\Services\SensitiveAccessLogger;
use Illuminate\Http\Request;

class SalaryController extends Controller
{
    public function __construct(
        private readonly EmployeeSalaryOverviewService $overview,
        private readonly SensitiveAccessLogger $sensitiveAccess,
    ) {}

    public function index(Request $request)
    {
        $employee = $this->employee($request);
        $this->sensitiveAccess->employeeSalaryView($request->user(), $employee, 'index', $request);

        $current = $this->overview->currentOverview($employee);
        $history = $this->overview->paginateHistory($employee, $request);
        $historyYears = $this->overview->historyYearOptions($employee);

        $filters = [
            'year' => $request->input('year', ''),
            'month' => $request->input('month', ''),
            'status' => $request->input('status', ''),
        ];

        return view('employee.salary.index', $current + compact('history', 'historyYears', 'filters'));
    }

    public function show(Request $request, PayrollEmployee $payrollEmployee)
    {
        $employee = $this->employee($request);
        $this->authorize('viewOwnSalary', $payrollEmployee);
        abort_unless($payrollEmployee->employee_id === $employee->id, 403);

        $payrollEmployee->load(['payrollPeriod', 'earnings', 'deductions']);
        $period = $payrollEmployee->payrollPeriod;
        $amountKind = $period?->status?->employeeAmountKind() ?? 'none';

        $this->sensitiveAccess->employeeSalaryView(
            $request->user(),
            $employee,
            'show',
            $request,
            ['payroll_employee_id' => $payrollEmployee->id],
        );

        return view('employee.salary.show', compact('payrollEmployee', 'employee', 'amountKind'));
    }

    private function employee(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        return $employee;
    }
}
