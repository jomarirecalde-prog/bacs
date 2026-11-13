<?php

namespace App\Http\Controllers;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\OvertimeAttachmentReportStatus;
use App\Http\Requests\OvertimeAttachment\DecideOvertimeAttachmentReportRequest;
use App\Http\Requests\OvertimeAttachment\ReturnOvertimeAttachmentReportRequest;
use App\Models\OvertimeAttachmentReport;
use App\Services\OvertimeAttachmentReportService;
use Illuminate\Http\Request;

class OvertimeAttachmentReportApprovalController extends Controller
{
    public function __construct(private readonly OvertimeAttachmentReportService $reports) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', OvertimeAttachmentReport::class);

        $scope = $request->string('scope')->toString() ?: null;
        $query = $this->reports->pendingFor($request->user());

        if ($scope === 'endorsement') {
            $query->whereIn('current_stage', [
                LeaveApprovalStage::ImmediateSupervisor,
                LeaveApprovalStage::DepartmentHead,
            ]);
        } elseif ($scope === 'final') {
            $query->where('current_stage', LeaveApprovalStage::CeoFinalApproval);
        }

        $reports = $query->paginate(15)->withQueryString();

        return view('overtime-attachment.approvals.index', [
            'reports' => $reports,
            'mode' => 'pending',
            'scope' => $scope ?? 'all',
        ]);
    }

    public function history(Request $request)
    {
        $this->authorize('viewAny', OvertimeAttachmentReport::class);

        $reports = $this->reports->historyFor($request->user())
            ->paginate(15)
            ->withQueryString();

        return view('overtime-attachment.approvals.index', [
            'reports' => $reports,
            'mode' => 'history',
        ]);
    }

    public function show(Request $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('view', $overtimeAttachmentReport);
        $overtimeAttachmentReport->load([
            'employee.department',
            'officialTimeRequest.officialTimeType',
            'activities',
            'assignments.user.employee',
            'actions.user',
        ]);

        return view('overtime-attachment.approvals.show', [
            'report' => $overtimeAttachmentReport,
            'canAct' => $request->user()->can('endorse', $overtimeAttachmentReport),
            'canPdf' => $request->user()->can('downloadPdf', $overtimeAttachmentReport),
        ]);
    }

    public function decide(DecideOvertimeAttachmentReportRequest $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->reports->decide(
            $overtimeAttachmentReport,
            $request->user(),
            LeaveDecision::from($request->validated('decision')),
            (string) ($request->validated('reason') ?? ''),
        );

        return back()->with('success', 'Your decision was recorded.');
    }

    public function returnForRevision(ReturnOvertimeAttachmentReportRequest $request, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->reports->returnForRevision(
            $overtimeAttachmentReport,
            $request->user(),
            (string) $request->validated('reason'),
        );

        return back()->with('success', 'The report was returned to the employee for revision.');
    }
}
