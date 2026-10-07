<?php

namespace App\Http\Controllers;

use App\Enums\LeaveDecision;
use App\Enums\OfficialTimeStatus;
use App\Http\Requests\OfficialTime\DecideOfficialTimeRequest;
use App\Http\Requests\OfficialTime\ReturnOfficialTimeRequest;
use App\Models\OfficialTimeRequest;
use App\Services\OfficialTimeService;
use Illuminate\Http\Request;

class OfficialTimeApprovalController extends Controller
{
    public function __construct(private readonly OfficialTimeService $officialTime) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OfficialTimeRequest::class);

        $scope = $request->string('scope')->toString() ?: null;
        $query = $this->officialTime->pendingFor($request->user());

        if ($scope === 'endorsement') {
            $query->where('status', OfficialTimeStatus::PendingSupervisor);
        } elseif ($scope === 'final') {
            $query->where('status', OfficialTimeStatus::PendingCeoFinalApproval);
        }

        $requests = $query->paginate(15)->withQueryString();

        return view('official-time.approvals.index', [
            'requests' => $requests,
            'mode' => 'pending',
            'scope' => $scope ?? 'all',
        ]);
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', OfficialTimeRequest::class);

        $requests = $this->officialTime->historyFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('official-time.approvals.index', [
            'requests' => $requests,
            'mode' => 'history',
        ]);
    }

    public function show(Request $request, OfficialTimeRequest $officialTimeRequest)
    {
        $this->authorize('view', $officialTimeRequest);
        $officialTimeRequest->load(['employee.department', 'designation', 'officialTimeType', 'assignments.user.employee', 'actions.user']);

        return view('official-time.approvals.show', [
            'ot' => $officialTimeRequest,
            'canAct' => $request->user()->can('endorse', $officialTimeRequest),
            'canDownload' => $request->user()->can('downloadAttachment', $officialTimeRequest),
        ]);
    }

    public function decide(DecideOfficialTimeRequest $request, OfficialTimeRequest $officialTimeRequest)
    {
        $this->officialTime->decide(
            $officialTimeRequest,
            $request->user(),
            LeaveDecision::from($request->validated('decision')),
            (string) ($request->validated('reason') ?? ''),
        );

        return back()->with('success', 'Your decision was recorded.');
    }

    public function returnForRevision(ReturnOfficialTimeRequest $request, OfficialTimeRequest $officialTimeRequest)
    {
        $this->officialTime->returnForRevision(
            $officialTimeRequest,
            $request->user(),
            (string) $request->validated('reason'),
        );

        return back()->with('success', 'The request was returned to the employee for revision.');
    }
}
