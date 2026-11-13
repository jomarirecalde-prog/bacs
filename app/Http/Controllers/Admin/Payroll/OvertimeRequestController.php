<?php

namespace App\Http\Controllers\Admin\Payroll;

use App\Enums\OvertimeRequestStatus;
use App\Http\Controllers\Controller;
use App\Models\OvertimeRequest;
use App\Models\OvertimeAttachmentReport;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use App\Services\OvertimeAttachmentReportPdfService;
use App\Services\OvertimeAttachmentReportService;
use App\Services\Payroll\OvertimeRequestSyncService;
use Illuminate\Http\Request;

class OvertimeRequestController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly OvertimeAttachmentReportService $attachmentReports,
        private readonly OvertimeAttachmentReportPdfService $attachmentPdf,
        private readonly OvertimeRequestSyncService $overtimeSync,
    ) {}

    public function index(Request $request, PayrollPeriod $period)
    {
        $this->authorize('view', $period);

        $imported = $this->overtimeSync->syncPeriod($period);
        $this->attachmentReports->reconcilePeriodReports(
            $period->start_date->toDateString(),
            $period->end_date->toDateString(),
            $request->user(),
        );

        $requests = OvertimeRequest::query()
            ->whereDate('attendance_date', '>=', $period->start_date)
            ->whereDate('attendance_date', '<=', $period->end_date)
            ->with(['employee:id,employee_number,full_name', 'attachmentReport'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('attendance_date')
            ->paginate(25)
            ->withQueryString();

        return view('admin.payroll.overtime.index', compact('period', 'requests', 'imported'));
    }

    public function decide(Request $request, OvertimeRequest $overtimeRequest)
    {
        $this->authorize('create', PayrollPeriod::class);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,deny'],
            'approved_minutes' => ['nullable', 'integer', 'min:0'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($data['decision'] === 'approve') {
            $minutes = $data['approved_minutes'] ?? $overtimeRequest->recorded_minutes;
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::Approved,
                'approved_minutes' => min($minutes, $overtimeRequest->recorded_minutes),
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);

            $attachment = $overtimeRequest->fresh('attachmentReport')->attachmentReport;
            if ($attachment?->pendingPayrollReview()) {
                $this->attachmentReports->decideByPayrollAdmin(
                    $attachment,
                    $request->user(),
                    'approve',
                    $data['review_notes'] ?? null,
                );
            }
        } else {
            $overtimeRequest->update([
                'status' => OvertimeRequestStatus::Denied,
                'approved_minutes' => 0,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
                'review_notes' => $data['review_notes'] ?? null,
            ]);
        }

        $this->audit->log($request->user(), 'overtime_decided', 'Payroll', $overtimeRequest->id, "OT {$data['decision']} for {$overtimeRequest->employee_id} on {$overtimeRequest->attendance_date->toDateString()}.");

        return back()->with('success', 'Overtime request updated.');
    }

    public function decideAttachment(Request $request, PayrollPeriod $period, OvertimeRequest $overtimeRequest)
    {
        $this->authorize('view', $period);

        abort_unless(
            $overtimeRequest->attendance_date >= $period->start_date
            && $overtimeRequest->attendance_date <= $period->end_date,
            404,
        );

        $report = $overtimeRequest->attachmentReport;
        abort_unless($report, 404);

        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject,return'],
            'review_notes' => ['required_if:decision,reject', 'required_if:decision,return', 'nullable', 'string', 'max:2000'],
        ]);

        $this->authorize('reviewPayroll', $report);

        $this->attachmentReports->decideByPayrollAdmin(
            $report,
            $request->user(),
            $data['decision'],
            $data['review_notes'] ?? null,
        );

        return back()->with('success', 'OT attachment report updated.');
    }

    public function attachmentPdf(Request $request, PayrollPeriod $period, OvertimeAttachmentReport $overtimeAttachmentReport)
    {
        $this->authorize('view', $period);
        $this->authorize('downloadPdf', $overtimeAttachmentReport);

        $ot = $overtimeAttachmentReport->overtimeRequest;
        abort_unless(
            $ot
            && $ot->attendance_date >= $period->start_date
            && $ot->attendance_date <= $period->end_date,
            404,
        );

        return $this->attachmentPdf->stream($overtimeAttachmentReport);
    }
}
