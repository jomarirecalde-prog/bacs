<?php

namespace App\Http\Controllers\Employee;

use App\Enums\LeaveType;
use App\Enums\SpecialLeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\StoreLeaveApplicationRequest;
use App\Models\LeaveApplication;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveTypeRecord;
use App\Services\LeaveApplicationService;
use App\Services\LeaveBalanceService;
use App\Services\LeaveDayCalculator;
use App\Services\LeaveFormPdfService;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class LeaveApplicationController extends Controller
{
    public function __construct(
        private readonly LeaveApplicationService $leaves,
        private readonly LeaveBalanceService $balances,
        private readonly LeaveDayCalculator $days,
        private readonly LeaveFormPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $employee = $this->employee($request);
        $this->authorize('viewAny', LeaveApplication::class);

        $applications = LeaveApplication::query()
            ->ownedBy($employee)
            ->with(['assignments', 'department'])
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('end_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('start_date', '<=', $request->query('to')))
            ->latest('date_filed')
            ->paginate(15)
            ->withQueryString();

        return view('employee.leave.index', [
            'applications' => $applications,
            'filters' => $request->only(['q', 'status', 'from', 'to']),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorize('create', LeaveApplication::class);
        $employee = $this->employee($request)->load(['department', 'workSchedule']);
        $year = (int) ManilaTime::now()->year;

        return view('employee.leave.apply', [
            'employee' => $employee,
            'balances' => $this->balances->snapshot($employee, $year),
            'types' => LeaveType::cases(),
            'specialTypes' => SpecialLeaveType::cases(),
            'today' => ManilaTime::todayDate(),
        ]);
    }

    public function previewDays(Request $request)
    {
        $this->authorize('create', LeaveApplication::class);
        $employee = $this->employee($request);
        $data = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'leave_type' => ['required'],
            'special_leave_type' => ['nullable'],
        ]);

        $type = LeaveType::from($data['leave_type']);
        $special = filled($data['special_leave_type'] ?? null) ? SpecialLeaveType::tryFrom($data['special_leave_type']) : null;
        $days = $this->days->days($employee, $data['start_date'], $data['end_date'], $type, $special);

        return response()->json(['days' => $days]);
    }

    public function store(StoreLeaveApplicationRequest $request)
    {
        $employee = $this->employee($request);
        $application = $this->leaves->submit($employee, $request->user(), $request->validated());
        $this->leaves->afterSubmit($application);

        return redirect()
            ->route('employee.leave.show', $application)
            ->with('success', "Leave application {$application->application_number} was submitted.");
    }

    public function show(Request $request, LeaveApplication $application)
    {
        $this->authorize('view', $application);
        abort_unless($application->employee_id === $this->employee($request)->id, 403);

        return view('employee.leave.show', $this->pdf->viewData($application) + [
            'canCancel' => $request->user()->can('cancel', $application),
        ]);
    }

    public function calendar(Request $request)
    {
        $employee = $this->employee($request);
        $month = (int) ($request->query('month') ?: ManilaTime::now()->month);
        $year = (int) ($request->query('year') ?: ManilaTime::now()->year);
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        $applications = LeaveApplication::query()
            ->ownedBy($employee)
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start)
            ->orderByDesc('date_filed')
            ->get();

        return view('employee.leave.calendar', [
            'employee' => $employee,
            'applications' => $applications,
            'month' => $month,
            'year' => $year,
            'balances' => $this->balances->snapshot($employee, $year),
        ]);
    }

    public function balances(Request $request)
    {
        $employee = $this->employee($request);
        $this->authorize('view', $employee);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);

        return view('employee.leave.balances', [
            'employee' => $employee->load('department'),
            'year' => $year,
            'balances' => $this->balances->snapshot($employee, $year),
            'types' => LeaveTypeRecord::query()->active()->orderBy('sort_order')->get(),
        ]);
    }

    public function adjustmentHistory(Request $request)
    {
        $employee = $this->employee($request);
        $this->authorize('viewLeaveAdjustments', $employee);

        $year = (int) ($request->integer('year') ?: ManilaTime::now()->year);

        $adjustments = LeaveBalanceAdjustment::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->when($request->filled('leave_type_code'), fn ($q) => $q->where('leave_type_code', $request->string('leave_type_code')))
            ->with(['updatedBy', 'leaveApplication'])
            ->latest('recorded_at')
            ->paginate(20)
            ->withQueryString();

        return view('employee.leave.adjustments', [
            'employee' => $employee->load('department'),
            'year' => $year,
            'adjustments' => $adjustments,
            'types' => LeaveTypeRecord::query()->active()->orderBy('sort_order')->get(),
            'filters' => $request->only(['leave_type_code', 'year']),
        ]);
    }

    public function cancel(Request $request, LeaveApplication $application)
    {
        $this->authorize('cancel', $application);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);
        $this->leaves->cancel($application, $request->user(), $data['reason'] ?? '');

        return back()->with('success', "Leave application {$application->application_number} was cancelled.");
    }

    public function pdf(Request $request, LeaveApplication $application)
    {
        $this->authorize('download', $application);
        abort_unless($application->employee_id === $this->employee($request)->id, 403);

        return $this->pdf->download($application);
    }

    public function print(Request $request, LeaveApplication $application)
    {
        $this->authorize('download', $application);
        abort_unless($application->employee_id === $this->employee($request)->id, 403);

        return view('leave.print', $this->pdf->viewData($application));
    }

    private function employee(Request $request)
    {
        $employee = $request->user()->employee;
        abort_unless($employee, 403, 'Your account is not linked to an employee profile.');

        return $employee;
    }
}
