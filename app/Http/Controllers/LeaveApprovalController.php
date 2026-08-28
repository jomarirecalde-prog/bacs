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

        $applications = $this->leaves->pendingFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('leave.approvals.index', [
            'applications' => $applications,
            'mode' => 'pending',
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
