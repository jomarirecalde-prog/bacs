<?php

namespace App\Http\Controllers;

use App\Enums\LeaveDecision;
use App\Http\Requests\Leave\DecideLeaveApplicationRequest;
use App\Models\LeaveApplication;
use App\Services\LeaveApplicationService;
use App\Services\LeaveFormPdfService;
use Illuminate\Http\Request;

class LeaveApprovalController extends Controller
{
    public function __construct(
        private readonly LeaveApplicationService $leaves,
        private readonly LeaveFormPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', LeaveApplication::class);

        $scope = $request->string('scope')->toString() ?: null;
        $query = $this->leaves->pendingFor($request->user());

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('employee')) {
            $term = '%'.$request->string('employee')->toString().'%';
            $query->whereHas('employee', function ($employee) use ($term) {
                $employee->where('full_name', 'like', $term)
                    ->orWhere('employee_number', 'like', $term);
            });
        }

        if ($request->filled('filed_from')) {
            $query->whereDate('date_filed', '>=', $request->date('filed_from'));
        }

        if ($request->filled('filed_to')) {
            $query->whereDate('date_filed', '<=', $request->date('filed_to'));
        }

        if ($scope === 'endorsement') {
            $query->whereIn('current_stage', [
                \App\Enums\LeaveApprovalStage::ImmediateSupervisor,
                \App\Enums\LeaveApprovalStage::DepartmentHead,
            ]);
        } elseif ($scope === 'final') {
            $query->where('status', \App\Enums\LeaveStatus::PendingCeoFinalApproval)
                ->where('current_stage', \App\Enums\LeaveApprovalStage::CeoFinalApproval);
        }

        $applications = $query->paginate(15)->withQueryString();

        return view('leave.approvals.index', [
            'applications' => $applications,
            'mode' => 'pending',
            'scope' => $scope ?? 'all',
            'filters' => $request->only(['status', 'employee', 'filed_from', 'filed_to']),
        ]);
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', LeaveApplication::class);

        $applications = $this->leaves->historyFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('leave.approvals.index', [
            'applications' => $applications,
            'mode' => 'history',
            'filters' => [],
        ]);
    }

    public function show(Request $request, LeaveApplication $application)
    {
        $this->authorize('view', $application);

        return view('leave.approvals.show', $this->pdf->viewData($application) + [
            'canApprove' => $request->user()->can('approve', $application),
            'canProcessHr' => $request->user()->can('processHr', $application),
        ]);
    }

    public function decide(DecideLeaveApplicationRequest $request, LeaveApplication $application)
    {
        $this->leaves->decide(
            $application,
            $request->user(),
            LeaveDecision::from($request->validated('decision')),
            (string) ($request->validated('reason') ?? ''),
            $request->validated('signature')
        );

        return back()->with('success', 'Your leave approval decision was recorded.');
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
