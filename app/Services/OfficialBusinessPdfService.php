<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Enums\OfficialBusinessStatus;
use App\Models\OfficialBusinessApprovalAssignment;
use App\Models\OfficialBusinessRequest;
use App\Support\ManilaTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class OfficialBusinessPdfService
{
    public function download(OfficialBusinessRequest $request): Response
    {
        $pdf = Pdf::loadView('official-business.official-form', $this->viewData($request))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($request->reference_number.'-official-business.pdf');
    }

    public function stream(OfficialBusinessRequest $request): Response
    {
        $pdf = Pdf::loadView('official-business.official-form', $this->viewData($request))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream($request->reference_number.'-official-business.pdf');
    }

    /** @return array<string, mixed> */
    public function viewData(OfficialBusinessRequest $request): array
    {
        $request->loadMissing([
            'employee.department',
            'participants.employee.department',
            'assignments',
        ]);

        $participantLines = $request->participants->take(10)->values();
        while ($participantLines->count() < 10) {
            $participantLines->push(null);
        }

        $approvedBy = $this->assignmentForStage($request, LeaveApprovalStage::AdministrativeHead)
            ?? $this->assignmentForStage($request, LeaveApprovalStage::CeoFinalApproval);
        $isApproved = in_array($request->status, [OfficialBusinessStatus::Approved, OfficialBusinessStatus::Completed], true);
        $approvedAt = $request->approved_at ?? $approvedBy?->acted_at;
        $approvedEmblemDate = $approvedAt
            ? ManilaTime::formatDateTime($approvedAt, 'M j, Y g:i A')
            : null;

        return [
            'request' => $request,
            'participantLines' => $participantLines,
            'issuedDate' => ManilaTime::formatDateTime($request->approved_at ?? $request->submitted_at ?? now(), 'F j, Y'),
            'filedDate' => optional($request->date_filed)?->format('F j, Y') ?? '—',
            'letterheadSrc' => $this->letterheadSrc(),
            'requestedBy' => $request->employee,
            'approvedBy' => $approvedBy,
            'checkedBy' => $this->assignmentForStage($request, LeaveApprovalStage::ImmediateSupervisor),
            'showApprovedEmblem' => $isApproved,
            'approvedEmblemDate' => $approvedEmblemDate,
            'print' => true,
        ];
    }

    private function assignmentForStage(OfficialBusinessRequest $request, LeaveApprovalStage $stage): ?OfficialBusinessApprovalAssignment
    {
        return $request->assignments
            ->where('stage', $stage)
            ->first(fn ($row) => $row->isApproved());
    }

    private function letterheadSrc(): ?string
    {
        $path = public_path('images/ot-attachment-letterhead.png');
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
