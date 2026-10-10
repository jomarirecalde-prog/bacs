<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\SalaryType;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeDeduction;
use App\Models\EmployeeSalaryHistory;
use App\Models\PayrollDeductionType;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use App\Services\Payroll\EmployeeSalaryService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeSalaryController extends Controller
{
    public function __construct(
        private readonly EmployeeSalaryService $salaries,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Employee $employee)
    {
        $this->authorize('create', PayrollPeriod::class);

        $employee->load(['designation', 'department']);

        $history = $employee->salaryHistory()
            ->with(['designation:id,designation_name', 'createdBy:id,name'])
            ->orderByDesc('effective_from')
            ->paginate(15);

        $current = $this->salaries->current($employee);

        $deductionTypes = PayrollDeductionType::query()->where('is_active', true)->orderBy('sort_order')->get();
        $recurringDeductions = $employee->payrollDeductions()
            ->where('is_active', true)
            ->with('deductionType')
            ->orderBy('deduction_type_id')
            ->get();
        $benefits = $employee->payrollBenefits()
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->get();

        $designationDefaults = null;
        if ($employee->designation) {
            $d = $employee->designation;
            $designationDefaults = [
                'pay_type' => $d->default_pay_type?->value,
                'basic_salary' => $d->default_basic_salary,
                'monthly_salary' => $d->default_basic_salary,
                'semi_monthly_salary' => $d->default_semi_monthly_salary,
                'daily_rate' => $d->default_daily_rate,
                'hourly_rate' => $d->default_hourly_rate,
                'working_hours_per_day' => $d->default_working_hours_per_day,
                'working_days_basis' => $d->default_working_days_per_period,
            ];
        }

        $activeBenefit = $employee->payrollBenefits()
            ->where('is_active', true)
            ->orderByDesc('effective_from')
            ->first();

        return view('admin.payroll.salary.index', compact(
            'employee',
            'history',
            'current',
            'deductionTypes',
            'recurringDeductions',
            'benefits',
            'designationDefaults',
            'activeBenefit',
        ));
    }

    public function store(Request $request, Employee $employee)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'designation_id' => ['nullable', 'exists:designations,id'],
            'salary_type' => ['required', Rule::enum(SalaryType::class)],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'gross_compensation' => ['required', 'numeric', 'min:0'],
            'working_hours_per_day' => ['nullable', 'integer', 'min:1', 'max:24'],
            'working_days_basis' => ['nullable', 'integer', 'min:1', 'max:31'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data['amount'] = $data['basic_salary'];
        $data = $this->salaries->normalizeAmounts($data);

        $this->salaries->assign($employee, $data, $request->user());

        return back()->with('success', 'Salary assignment saved.');
    }

    public function storeBenefit(Request $request, Employee $employee)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
        ]);

        EmployeeBenefit::query()->create([
            'employee_id' => $employee->id,
            'benefit_code' => 'de_minimis',
            'label' => 'De Minimis',
            'amount' => $data['amount'],
            'frequency' => 'semi_monthly',
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'is_active' => true,
        ]);

        $this->audit->log($request->user(), 'benefit_assigned', 'Payroll', $employee->id, "De minimis benefit updated for {$employee->fullName()}.");

        return back()->with('success', 'De minimis benefit saved.');
    }

    public function storeDeduction(Request $request, Employee $employee)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'deduction_type_id' => ['required', 'exists:payroll_deduction_types,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        EmployeeDeduction::query()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $data['deduction_type_id'],
            'amount' => $data['amount'],
            'frequency' => 'semi_monthly',
            'effective_from' => $data['effective_from'] ?? now()->toDateString(),
            'is_active' => true,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->audit->log($request->user(), 'deduction_assigned', 'Payroll', $employee->id, "Recurring deduction added for {$employee->fullName()}.");

        return back()->with('success', 'Recurring deduction saved.');
    }

    public function updateBenefit(Request $request, Employee $employee, EmployeeBenefit $benefit)
    {
        $this->authorize('create', PayrollPeriod::class);
        abort_unless($benefit->employee_id === $employee->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $benefit->update([
            'amount' => $data['amount'],
            'effective_from' => $data['effective_from'] ?? $benefit->effective_from,
            'is_active' => $data['is_active'] ?? $benefit->is_active,
        ]);

        $this->audit->log($request->user(), 'benefit_updated', 'Payroll', $benefit->id, "De minimis updated for {$employee->fullName()}.");

        return back()->with('success', 'De minimis record updated.');
    }

    public function updateDeduction(Request $request, Employee $employee, EmployeeDeduction $deduction)
    {
        $this->authorize('create', PayrollPeriod::class);
        abort_unless($deduction->employee_id === $employee->id, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'effective_from' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $deduction->update([
            'amount' => $data['amount'],
            'effective_from' => $data['effective_from'] ?? $deduction->effective_from,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $deduction->is_active,
            'notes' => array_key_exists('notes', $data) ? $data['notes'] : $deduction->notes,
        ]);

        $this->audit->log($request->user(), 'deduction_updated', 'Payroll', $deduction->id, "Recurring deduction updated for {$employee->fullName()}.");

        return back()->with('success', 'Recurring deduction updated.');
    }

    public function updateSalaryHistory(Request $request, Employee $employee, EmployeeSalaryHistory $salaryHistory)
    {
        $this->authorize('create', PayrollPeriod::class);
        abort_unless($salaryHistory->employee_id === $employee->id, 404);

        $data = $request->validate([
            'salary_type' => ['required', Rule::enum(SalaryType::class)],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'gross_compensation' => ['nullable', 'numeric', 'min:0'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'semi_monthly_salary' => ['nullable', 'numeric', 'min:0'],
            'daily_rate' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0'],
            'working_hours_per_day' => ['nullable', 'integer', 'min:1', 'max:24'],
            'working_days_basis' => ['nullable', 'integer', 'min:1', 'max:31'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $data = $this->salaries->normalizeAmounts($data);
        $this->salaries->assertPrimaryRatePresent($data);

        $this->salaries->updateHistory($salaryHistory, $data, $request->user());

        return back()->with('success', 'Salary record updated.');
    }
}
