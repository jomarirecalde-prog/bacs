<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Enums\OfficialTimeStatus;
use App\Enums\OvertimeAttachmentReportStatus;
use App\Models\OfficialTimeRequest;
use App\Models\OvertimeAttachmentApprovalAssignment;
use App\Models\OvertimeAttachmentReport;
use App\Support\ManilaTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class OvertimeAttachmentReportPdfService
{
    public function download(OvertimeAttachmentReport $report): Response
    {
        $pdf = Pdf::loadView('overtime-attachment.official-form', $this->viewData($report))
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($report->report_no.'-ot-attachment-report.pdf');
    }

    public function stream(OvertimeAttachmentReport $report): Response
    {
        $pdf = Pdf::loadView('overtime-attachment.official-form', $this->viewData($report))
            ->setPaper('a4', 'landscape')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream($report->report_no.'-ot-attachment-report.pdf');
    }

    /** @return array<string, mixed> */
    public function viewData(OvertimeAttachmentReport $report): array
    {
        $report->loadMissing([
            'employee.department',
            'employee.designation',
            'overtimeRequest',
            'officialTimeRequest',
            'activities',
            'assignments',
        ]);

        $endorsedBy = $this->assignmentForStage($report, LeaveApprovalStage::ImmediateSupervisor)
            ?? $this->assignmentForStage($report, LeaveApprovalStage::DepartmentHead);
        $approvedAssignment = $this->assignmentForStage($report, LeaveApprovalStage::CeoFinalApproval);
        $otApplication = $this->resolvedOtApplication($report);
        $otApplicationApproved = $otApplication?->status === OfficialTimeStatus::Approved;
        $otApprovedDate = $otApplication?->approved_at
            ? ManilaTime::formatDateTime($otApplication->approved_at, 'M j, Y')
            : null;

        $reviewedEmblemDate = $endorsedBy?->acted_at
            ? ManilaTime::formatDateTime($endorsedBy->acted_at, 'M j, Y')
            : $otApprovedDate;
        $approvedEmblemDate = $report->approved_at
            ? ManilaTime::formatDateTime($report->approved_at, 'M j, Y')
            : ($approvedAssignment?->acted_at
                ? ManilaTime::formatDateTime($approvedAssignment->acted_at, 'M j, Y')
                : $otApprovedDate);

        return [
            'report' => $report,
            'submittedDate' => ManilaTime::formatDateTime(
                $report->date_submitted ?? $report->submitted_at ?? now(),
                'F j, Y',
            ),
            'logoSrc' => $this->logoSrc(),
            'letterheadSrc' => $this->letterheadSrc(),
            'endorsedBy' => $endorsedBy,
            'approvedAssignment' => $approvedAssignment,
            'showReviewedEmblem' => $otApplicationApproved,
            'showApprovedEmblem' => $otApplicationApproved,
            'reviewedEmblemDate' => $reviewedEmblemDate,
            'approvedEmblemDate' => $approvedEmblemDate,
            'print' => true,
        ];
    }

    private function resolvedOtApplication(OvertimeAttachmentReport $report): ?OfficialTimeRequest
    {
        if ($report->official_time_request_id && $report->officialTimeRequest) {
            return $report->officialTimeRequest;
        }

        $date = $report->overtimeRequest?->attendance_date?->toDateString()
            ?? $report->date_coverage_start?->toDateString();

        if (! $date) {
            return null;
        }

        return OfficialTimeRequest::query()
            ->where('employee_id', $report->employee_id)
            ->whereDate('date', $date)
            ->where('status', OfficialTimeStatus::Approved)
            ->orderByDesc('approved_at')
            ->first();
    }

    private function assignmentForStage(
        OvertimeAttachmentReport $report,
        LeaveApprovalStage $stage,
    ): ?OvertimeAttachmentApprovalAssignment {
        return $report->assignments
            ->where('stage', $stage)
            ->first(fn ($row) => $row->isApproved());
    }

    private function logoSrc(): ?string
    {
        return $this->imageDataUri(public_path('images/bacs_logo_no_bg.png'));
    }

    private function letterheadSrc(): ?string
    {
        return $this->imageDataUri(public_path('images/ot-attachment-letterhead.png'));
    }

    private function imageDataUri(string $path): ?string
    {
        if (! is_file($path)) {
            return null;
        }

        $binary = file_get_contents($path);
        if ($binary === false) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($binary);
    }
}
