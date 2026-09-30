<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Models\TravelOrder;
use App\Support\ManilaTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class TravelOrderPdfService
{
    public function download(TravelOrder $order): Response
    {
        $pdf = Pdf::loadView('travel-order.official-form', $this->viewData($order))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->download($order->travel_order_number.'-travel-order.pdf');
    }

    public function stream(TravelOrder $order): Response
    {
        $pdf = Pdf::loadView('travel-order.official-form', $this->viewData($order))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream($order->travel_order_number.'-travel-order.pdf');
    }

    /** @return array<string, mixed> */
    public function viewData(TravelOrder $order): array
    {
        $order->loadMissing([
            'requester.department',
            'personnel.employee.department',
            'destinations',
            'assignments',
        ]);

        return [
            'order' => $order,
            'travelers' => $order->personnel->map(fn ($p) => $p->employee)->filter(),
            'issuedDate' => ManilaTime::formatDateTime($order->approved_at ?? $order->submitted_at ?? now(), 'F j, Y'),
            'logoSrc' => $this->logoSrc(),
            'checkedBy' => $this->assignmentForStage($order, LeaveApprovalStage::ImmediateSupervisor),
            'approvedBy' => $this->assignmentForStage($order, LeaveApprovalStage::AdministrativeHead)
                ?? $this->assignmentForStage($order, LeaveApprovalStage::CeoFinalApproval),
            'print' => true,
        ];
    }

    private function assignmentForStage(TravelOrder $order, LeaveApprovalStage $stage): ?\App\Models\TravelOrderApprovalAssignment
    {
        return $order->assignments
            ->where('stage', $stage)
            ->first(fn ($row) => $row->isApproved());
    }

    private function logoSrc(): ?string
    {
        $path = public_path('images/bacs_logo_no_bg.png');
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
