<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveDecision;
use App\Enums\LeaveStatus;
use App\Enums\LeaveType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Leave\ProcessLeaveHrRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Services\DirectoryCatalog;
use App\Services\LeaveApplicationService;
use App\Services\LeaveFormPdfService;
use Illuminate\Http\Request;

class LeaveApplicationController extends Controller
{
    public function __construct(
        private readonly LeaveApplicationService $leaves,
        private readonly LeaveFormPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAll', LeaveApplication::class);

        $applications = LeaveApplication::query()
            ->with(['employee.department', 'department', 'assignments'])
            ->search($request->query('q'))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('employee_id'), fn ($q) => $q->where('employee_id', $request->integer('employee_id')))
            ->when($request->filled('leave_type'), fn ($q) => $q->where('leave_type', $request->query('leave_type')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('from'), fn ($q) => $q->where('end_date', '>=', $request->query('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('start_date', '<=', $request->query('to')))
            ->latest('date_filed')
            ->paginate(20)
            ->withQueryString();

        return view('admin.leave.index', [
            'applications' => $applications,
            'departments' => app(DirectoryCatalog::class)->departments(),
            'employees' => Employee::query()->orderBy('last_name')->limit(500)->get(['id', 'first_name', 'last_name', 'full_name', 'employee_number']),
            'types' => LeaveType::cases(),
            'statuses' => LeaveStatus::cases(),
            'filters' => $request->only(['q', 'department_id', 'employee_id', 'leave_type', 'status', 'from', 'to']),
        ]);
    }

    public function show(Request $request, LeaveApplication $application)
    {
        $this->authorize('view', $application);

        return view('admin.leave.show', $this->pdf->viewData($application) + [
            'canProcessHr' => $request->user()->can('processHr', $application),
            'canApprove' => $request->user()->can('approve', $application),
        ]);
    }

    public function processHr(ProcessLeaveHrRequest $request, LeaveApplication $application)
    {
        $this->leaves->processHr($application, $request->user(), $request->validated());

        $label = $request->validated('decision') === LeaveDecision::Approved->value ? 'approved' : 'denied';

        return back()->with('success', "Leave application {$application->application_number} was {$label} by HR.");
    }

    public function pdf(LeaveApplication $application)
    {
        $this->authorize('download', $application);

        return $this->pdf->download($application);
    }

    public function print(LeaveApplication $application)
    {
        $this->authorize('download', $application);

        return view('leave.print', $this->pdf->viewData($application));
    }
}
