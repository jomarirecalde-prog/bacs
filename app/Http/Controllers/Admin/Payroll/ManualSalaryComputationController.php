<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\ManualSalaryComputationMode;
use App\Enums\ManualSalaryMonthlySubtype;
use App\Enums\ManualSalaryPayableIntent;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ManualSalaryComputation;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use App\Services\Payroll\ManualSalaryComputationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ManualSalaryComputationController extends Controller
{
    public function __construct(
        private readonly ManualSalaryComputationService $computations,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);

        $computation->load(['lines', 'overrides.user', 'payrollPeriod', 'preparedByUser', 'employee.department', 'employee.designation']);

        return view('admin.payroll.manual-computations.show', compact('employee', 'computation'));
    }

    public function create(Employee $employee)
    {
        $this->authorize('create', PayrollPeriod::class);

        $employee->load(['department', 'designation']);
        $payrollPeriods = PayrollPeriod::query()
            ->orderByDesc('start_date')
            ->limit(50)
            ->get(['id', 'period_name', 'start_date', 'end_date', 'status']);

        return view('admin.payroll.manual-computations.create', compact('employee', 'payrollPeriods'));
    }

    public function store(Request $request, Employee $employee)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $this->validatedPayload($request);
        $computation = $this->computations->createDraft($employee, $data, $request->user());

        $this->audit->log(
            $request->user(),
            'manual_salary_computation_created',
            'Payroll',
            $computation->id,
            "Manual salary computation draft for {$employee->fullName()}.",
        );

        return redirect()
            ->route('admin.payroll.employees.manual-computations.show', [$employee, $computation])
            ->with('success', 'Manual computation saved as draft.');
    }

    public function update(Request $request, Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);

        $data = $this->validatedPayload($request);
        $computation = $this->computations->updateDraft($computation, $data, $request->user());

        $this->audit->log(
            $request->user(),
            'manual_salary_computation_updated',
            'Payroll',
            $computation->id,
            "Manual salary computation #{$computation->id} updated.",
        );

        return redirect()
            ->route('admin.payroll.employees.manual-computations.show', [$employee, $computation])
            ->with('success', 'Computation updated.');
    }

    public function recalculate(Request $request, Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);

        $overrides = $request->validate([
            'overrides' => ['nullable', 'array'],
            'overrides.*.field_key' => ['required', 'string', 'max:128'],
            'overrides.*.manual_value' => ['required', 'numeric'],
            'overrides.*.reason' => ['required', 'string', 'max:2000'],
        ])['overrides'] ?? [];

        $this->computations->recalculate($computation, $request->user(), $overrides);

        return back()->with('success', 'Computation recalculated.');
    }

    public function submit(Request $request, Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);
        $this->computations->submit($computation, $request->user());

        return back()->with('success', 'Submitted for review.');
    }

    public function approve(Request $request, Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);

        $notes = $request->validate(['review_notes' => ['nullable', 'string', 'max:2000']])['review_notes'] ?? null;
        $this->computations->approve($computation, $request->user(), $notes);

        return back()->with('success', 'Computation approved.');
    }

    public function reject(Request $request, Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);

        $notes = $request->validate(['review_notes' => ['required', 'string', 'max:2000']])['review_notes'];
        $this->computations->reject($computation, $request->user(), $notes);

        return back()->with('success', 'Computation rejected.');
    }

    public function finalize(Request $request, Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);
        $this->computations->finalize($computation, $request->user());

        $this->audit->log(
            $request->user(),
            'manual_salary_computation_finalized',
            'Payroll',
            $computation->id,
            "Manual salary computation #{$computation->id} finalized.",
        );

        return back()->with('success', 'Computation finalized.');
    }

    public function print(Employee $employee, ManualSalaryComputation $computation)
    {
        $this->authorizeComputation($employee, $computation);
        $computation->load(['lines', 'overrides.user', 'payrollPeriod', 'preparedByUser', 'employee.department']);

        return view('admin.payroll.manual-computations.print', compact('employee', 'computation'));
    }

    private function authorizeComputation(Employee $employee, ManualSalaryComputation $computation): void
    {
        $this->authorize('create', PayrollPeriod::class);
        abort_unless($computation->employee_id === $employee->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        $request->merge([
            'payroll_period_id' => $request->filled('payroll_period_id') ? $request->payroll_period_id : null,
            'coverage_start' => $request->filled('coverage_start') ? $request->input('coverage_start') : null,
            'coverage_end' => $request->filled('coverage_end') ? $request->input('coverage_end') : null,
        ]);

        $mode = $request->input('computation_mode');

        $rules = [
            'computation_mode' => ['required', Rule::enum(ManualSalaryComputationMode::class)],
            'payable_intent' => ['required', Rule::enum(ManualSalaryPayableIntent::class)],
            'payroll_period_id' => ['nullable', 'exists:payroll_periods,id'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'attendance' => ['nullable', 'array'],
            'attendance.scheduled_days' => ['nullable', 'integer', 'min:0'],
            'attendance.regular_hours' => ['nullable', 'numeric', 'min:0'],
            'attendance.absent_days' => ['nullable', 'numeric', 'min:0'],
            'attendance.late_minutes' => ['nullable', 'integer', 'min:0'],
            'attendance.undertime_minutes' => ['nullable', 'integer', 'min:0'],
            'attendance.approved_ot_hours' => ['nullable', 'numeric', 'min:0'],
            'attendance.paid_leave_days' => ['nullable', 'numeric', 'min:0'],
            'attendance.unpaid_leave_days' => ['nullable', 'numeric', 'min:0'],
            'custom_lines' => ['nullable', 'array'],
            'custom_lines.*.direction' => ['required_with:custom_lines', Rule::in(['earning', 'deduction'])],
            'custom_lines.*.label' => ['required_with:custom_lines', 'string', 'max:255'],
            'custom_lines.*.amount' => ['required_with:custom_lines', 'numeric', 'min:0'],
            'custom_lines.*.category' => ['nullable', 'string', 'max:64'],
            'overrides' => ['nullable', 'array'],
            'overrides.*.field_key' => ['required_with:overrides', 'string', 'max:128'],
            'overrides.*.manual_value' => ['required_with:overrides', 'numeric'],
            'overrides.*.reason' => ['required_with:overrides', 'string', 'max:2000'],
        ];

        if ($mode === ManualSalaryComputationMode::Period->value) {
            $rules['coverage_start'] = ['required_without:payroll_period_id', 'nullable', 'date'];
            $rules['coverage_end'] = ['required_without:payroll_period_id', 'nullable', 'date', 'after_or_equal:coverage_start'];
        } else {
            $rules['payroll_year'] = ['required', 'integer', 'min:2000', 'max:2100'];
            $rules['payroll_month'] = ['required', 'integer', 'min:1', 'max:12'];
            $rules['monthly_subtype'] = ['required', Rule::enum(ManualSalaryMonthlySubtype::class)];
            $rules['coverage_start'] = ['required_if:monthly_subtype,partial_month', 'nullable', 'date'];
            $rules['coverage_end'] = ['required_if:monthly_subtype,partial_month', 'nullable', 'date', 'after_or_equal:coverage_start'];
        }

        return $request->validate($rules);
    }
}
