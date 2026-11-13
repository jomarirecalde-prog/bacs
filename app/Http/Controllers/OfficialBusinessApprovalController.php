<?php

namespace App\Http\Controllers;

use App\Enums\LeaveDecision;
use App\Enums\OfficialBusinessStatus;
use App\Http\Requests\OfficialBusiness\DecideOfficialBusinessRequest;
use App\Models\OfficialBusinessRequest;
use App\Services\OfficialBusinessPdfService;
use App\Services\OfficialBusinessService;
use Illuminate\Http\Request;

class OfficialBusinessApprovalController extends Controller
{
    public function __construct(
        private readonly OfficialBusinessService $officialBusiness,
        private readonly OfficialBusinessPdfService $pdf,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OfficialBusinessRequest::class);

        $this->officialBusiness->refreshStuckApprovalQueues();

        $scope = $request->string('scope')->toString() ?: null;
        $query = $this->officialBusiness->pendingFor($request->user());

        if ($scope === 'endorsement') {
            $query->whereIn('status', [
                OfficialBusinessStatus::PendingSupervisor,
                OfficialBusinessStatus::PendingDepartmentHead,
            ]);
        } elseif ($scope === 'final') {
            $query->where('status', OfficialBusinessStatus::PendingCeoFinalApproval);
        }

        $requests = $query->paginate(15)->withQueryString();

        return view('official-business.approvals.index', [
            'requests' => $requests,
            'mode' => 'pending',
            'scope' => $scope ?? 'all',
        ]);
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', OfficialBusinessRequest::class);

        $requests = $this->officialBusiness->historyFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('official-business.approvals.index', [
            'requests' => $requests,
            'mode' => 'history',
            'scope' => 'all',
        ]);
    }

    public function show(Request $request, OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('view', $officialBusinessRequest);
        $officialBusinessRequest->load([
            'employee.department',
            'participants.employee.department',
            'assignments.user.employee',
            'actions.user',
        ]);

        return view('official-business.approvals.show', [
            'obRequest' => $officialBusinessRequest,
            'canEndorse' => $request->user()->can('endorse', $officialBusinessRequest),
            'canDownload' => $request->user()->can('download', $officialBusinessRequest),
        ]);
    }

    public function decide(DecideOfficialBusinessRequest $request, OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->officialBusiness->decide(
            $officialBusinessRequest,
            $request->user(),
            LeaveDecision::from($request->validated('decision')),
            (string) ($request->validated('reason') ?? ''),
            $request->validated('signature')
        );

        return back()->with('success', 'Your decision was recorded.');
    }

    public function pdf(OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('download', $officialBusinessRequest);

        return $this->pdf->download($officialBusinessRequest);
    }

    public function preview(OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('download', $officialBusinessRequest);

        return $this->pdf->stream($officialBusinessRequest);
    }

    public function print(OfficialBusinessRequest $officialBusinessRequest)
    {
        $this->authorize('download', $officialBusinessRequest);

        return view('official-business.print', $this->pdf->viewData($officialBusinessRequest));
    }
}
