<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollAdjustment;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class PayrollAdjustmentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(PayrollPeriod $period)
    {
        $this->authorize('view', $period);

        $adjustments = PayrollAdjustment::query()
            ->where('target_payroll_period_id', $period->id)
            ->with(['employee:id,employee_number,full_name', 'sourcePeriod:id,period_name'])
            ->orderByDesc('id')
            ->paginate(20);

        return view('admin.payroll.adjustments.index', compact('period', 'adjustments'));
    }

    public function create(PayrollPeriod $period)
    {
        $this->authorize('update', $period);

        return view('admin.payroll.adjustments.create', compact('period'));
    }

    public function store(Request $request, PayrollPeriod $period)
    {
        $this->authorize('update', $period);

        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'source_payroll_period_id' => ['nullable', 'exists:payroll_periods,id'],
            'adjustment_type' => ['required', 'string', 'max:120'],
            'direction' => ['required', 'in:earning,deduction'],
            'adjustment_amount' => ['required', 'numeric', 'min:0.01'],
            'original_amount' => ['nullable', 'numeric'],
            'corrected_amount' => ['nullable', 'numeric'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $adjustment = PayrollAdjustment::query()->create([
            ...$data,
            'target_payroll_period_id' => $period->id,
            'approved_by' => $request->user()->id,
            'created_by' => $request->user()->id,
            'status' => 'approved',
        ]);

        $this->audit->log($request->user(), 'payroll_adjustment_created', 'Payroll', $adjustment->id, "Adjustment for employee {$adjustment->employee_id} on period {$period->id}.");

        return redirect()->route('admin.payroll.adjustments.index', $period)->with('success', 'Adjustment recorded. Recompute payroll to apply.');
    }
}
