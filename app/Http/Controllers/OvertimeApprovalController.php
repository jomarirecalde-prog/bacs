<?php

namespace App\Http\Controllers;

use App\Enums\LeaveDecision;
use App\Models\OvertimeRequest;
use App\Services\Payroll\OvertimeApprovalService;
use Illuminate\Http\Request;

class OvertimeApprovalController extends Controller
{
    public function __construct(private readonly OvertimeApprovalService $overtime) {}

    public function index(Request $request)
    {
        $scope = $request->string('scope')->toString() ?: null;

        $requests = $this->overtime->pendingFor($request->user(), $scope)
            ->paginate(15)
            ->withQueryString();

        return view('overtime.approvals.index', [
            'requests' => $requests,
            'scope' => $scope ?? 'all',
        ]);
    }

    public function show(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->authorize('view', $overtimeRequest);
        $overtimeRequest->load(['employee.department', 'approvalAssignments.user.employee']);

        return view('overtime.approvals.show', [
            'overtimeRequest' => $overtimeRequest,
            'canApprove' => $this->overtime->userCanAct($request->user(), $overtimeRequest),
        ]);
    }

    public function decide(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->authorize('decide', $overtimeRequest);

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,denied'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'approved_minutes' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->overtime->decide(
            $overtimeRequest,
            $request->user(),
            LeaveDecision::from($validated['decision']),
            (string) ($validated['reason'] ?? ''),
            isset($validated['approved_minutes']) ? (int) $validated['approved_minutes'] : null,
        );

        return back()->with('success', 'Your decision was recorded.');
    }
}
