<?php

namespace App\Http\Controllers;

use App\Enums\LeaveDecision;
use App\Http\Requests\TravelOrder\DecideTravelOrderRequest;
use App\Models\AttendanceCorrectionRequest;
use App\Services\PardonApprovalService;
use Illuminate\Http\Request;

class PardonApprovalController extends Controller
{
    public function __construct(private readonly PardonApprovalService $pardon) {}

    public function index(Request $request)
    {
        $scope = $request->string('scope')->toString() ?: null;

        $requests = $this->pardon->pendingFor($request->user(), $scope)
            ->paginate(15)
            ->withQueryString();

        return view('pardon.approvals.index', [
            'requests' => $requests,
            'scope' => $scope ?? 'all',
        ]);
    }

    public function show(AttendanceCorrectionRequest $correction)
    {
        $correction->load(['employee.department', 'approvalAssignments.user.employee']);

        return view('pardon.approvals.show', ['correction' => $correction]);
    }

    public function decide(Request $request, AttendanceCorrectionRequest $correction)
    {
        $validated = $request->validate([
            'decision' => ['required', 'in:approved,denied'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->pardon->decide(
            $correction,
            $request->user(),
            LeaveDecision::from($validated['decision']),
            (string) ($validated['reason'] ?? '')
        );

        return back()->with('success', 'Your decision was recorded.');
    }
}
