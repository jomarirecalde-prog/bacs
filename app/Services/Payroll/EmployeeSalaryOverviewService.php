<?php

namespace App\Services\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\EmployeeSalaryHistory;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class EmployeeSalaryOverviewService
{
    public function __construct(private readonly EmployeeSalaryService $salaries) {}

    public function resolveCurrentPeriod(): ?PayrollPeriod
    {
        return PayrollPeriod::query()
            ->whereNotIn('status', [PayrollPeriodStatus::Cancelled])
            ->orderByDesc('start_date')
            ->first();
    }

    /**
     * @return array{
     *     employee: Employee,
     *     period: ?PayrollPeriod,
     *     salaryConfig: ?EmployeeSalaryHistory,
     *     payrollEmployee: ?PayrollEmployee,
     *     amountKind: string
     * }
     */
    public function currentOverview(Employee $employee): array
    {
        $employee->loadMissing(['department', 'designation']);
        $period = $this->resolveCurrentPeriod();
        $salaryConfig = $this->salaries->current($employee);

        $payrollEmployee = null;
        $amountKind = 'none';

        if ($period) {
            $payrollEmployee = PayrollEmployee::query()
                ->where('payroll_period_id', $period->id)
                ->where('employee_id', $employee->id)
                ->with(['earnings', 'deductions', 'payrollPeriod'])
                ->first();

            if ($payrollEmployee && $period->status) {
                $amountKind = $period->status->employeeAmountKind();
            }
        }

        return compact('employee', 'period', 'salaryConfig', 'payrollEmployee', 'amountKind');
    }

    public function paginateHistory(Employee $employee, Request $request): LengthAwarePaginator
    {
        $query = PayrollEmployee::query()
            ->where('payroll_employees.employee_id', $employee->id)
            ->with('payrollPeriod')
            ->join('payroll_periods', 'payroll_periods.id', '=', 'payroll_employees.payroll_period_id')
            ->select('payroll_employees.*')
            ->orderByDesc('payroll_periods.end_date');

        if ($request->filled('year')) {
            $query->whereYear('payroll_periods.end_date', (int) $request->input('year'));
        }

        if ($request->filled('month')) {
            $query->whereMonth('payroll_periods.end_date', (int) $request->input('month'));
        }

        $statusFilter = (string) $request->input('status', '');
        if ($statusFilter !== '') {
            $statuses = PayrollPeriodStatus::forEmployeeSalaryFilter($statusFilter);
            if ($statuses !== []) {
                $query->whereIn('payroll_periods.status', array_map(
                    fn (PayrollPeriodStatus $status) => $status->value,
                    $statuses,
                ));
            }
        }

        return $query->paginate(12)->withQueryString();
    }

    /** @return list<int> */
    public function historyYearOptions(Employee $employee): array
    {
        return PayrollEmployee::query()
            ->where('employee_id', $employee->id)
            ->with('payrollPeriod:id,end_date')
            ->get()
            ->pluck('payrollPeriod.end_date')
            ->filter()
            ->map(fn ($date) => (int) $date->format('Y'))
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }
}
