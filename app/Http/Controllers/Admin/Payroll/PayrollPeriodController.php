<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Http\Controllers\Controller;
use App\Models\PayrollPeriod;
use App\Services\Payroll\PayrollAttendanceAggregator;
use App\Services\Payroll\PayrollPeriodService;
use App\Services\Payroll\PayrollPeriodValidationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PayrollPeriodController extends Controller
{
    public function __construct(
        private readonly PayrollPeriodService $periods,
        private readonly PayrollPeriodValidationService $validation,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', PayrollPeriod::class);

        $periods = PayrollPeriod::query()
            ->withCount('attendanceSummaries')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.payroll.periods.index', [
            'periods' => $periods,
            'statuses' => PayrollPeriodStatus::cases(),
        ]);
    }

    public function create()
    {
        $this->authorize('create', PayrollPeriod::class);

        $suggested = PayrollAttendanceAggregator::suggestPeriodFromDtr();

        return view('admin.payroll.periods.create', compact('suggested'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'period_name' => ['required', 'string', 'max:150'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'payroll_date' => ['nullable', 'date'],
        ]);

        $period = $this->periods->create($data, $request->user());

        return redirect()->route('admin.payroll.periods.show', $period)->with('success', 'Payroll period created.');
    }

    public function show(PayrollPeriod $period)
    {
        $this->authorize('view', $period);

        $period->load(['createdBy:id,name', 'approvedBy:id,name', 'finalizedBy:id,name']);
        $period->loadCount('payrollEmployees');

        $summaries = $period->attendanceSummaries()
            ->join('employees', 'employees.id', '=', 'payroll_attendance_summary.employee_id')
            ->with(['employee:id,employee_number,full_name,department_id,designation_id', 'employee.department:id,name', 'employee.designation:id,designation_name'])
            ->orderBy('employees.full_name')
            ->select('payroll_attendance_summary.*')
            ->paginate(25);

        $finalizeCheck = $this->validation->forFinalize($period);

        return view('admin.payroll.periods.show', compact('period', 'summaries', 'finalizeCheck'));
    }

    public function compute(Request $request, PayrollPeriod $period)
    {
        $this->authorize('compute', $period);

        $result = $this->periods->computeAttendance($period, $request->user());

        return back()->with('success', "Attendance summary computed for {$result['employees']} employees.");
    }

    public function computePayroll(Request $request, PayrollPeriod $period)
    {
        $this->authorize('compute', $period);

        $result = $this->periods->computePayroll($period, $request->user());

        return back()->with('success', "Payroll computed for {$result['employees']} employees.");
    }

    public function updateStatus(Request $request, PayrollPeriod $period)
    {
        $this->authorize('update', $period);

        $data = $request->validate([
            'status' => ['required', Rule::enum(PayrollPeriodStatus::class)],
            'acknowledge_warnings' => ['nullable', 'boolean'],
        ]);

        $this->periods->transition(
            $period,
            PayrollPeriodStatus::from($data['status']),
            $request->user(),
            ['acknowledge_warnings' => $request->boolean('acknowledge_warnings')]
        );

        return back()->with('success', 'Payroll period status updated.');
    }

    public function notifyPayslips(Request $request, PayrollPeriod $period)
    {
        $this->authorize('update', $period);

        $sent = $this->periods->notifyPayslips($period, $request->user());

        return back()->with('success', "Payslip email sent to {$sent} employee(s).");
    }
}
