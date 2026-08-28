<?php

namespace App\Services;

use App\Models\LeaveApplication;
use App\Models\LeaveTypeRecord;
use App\Support\ManilaTime;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class LeaveFormPdfService
{
    public function __construct(private readonly LeaveBalanceService $balances) {}

    public function download(LeaveApplication $application): Response
    {
        $pdf = Pdf::loadView('leave.official-form', $this->viewData($application))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        $filename = $application->application_number.'-leave-form.pdf';

        return $pdf->download($filename);
    }

    public function stream(LeaveApplication $application): Response
    {
        $pdf = Pdf::loadView('leave.official-form', $this->viewData($application))
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', true)
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans');

        return $pdf->stream($application->application_number.'-leave-form.pdf');
    }

    public function viewData(LeaveApplication $application): array
    {
        $application->loadMissing([
            'employee.department',
            'employee.workSchedule',
            'assignments.user.employee',
            'conflicts',
        ]);

        $year = (int) ($application->start_date?->year ?? ManilaTime::now()->year);
        $balances = $this->balances->snapshot($application->employee, $year);
        $entitlements = LeaveTypeRecord::query()->active()->orderBy('sort_order')->get()->keyBy('code');

        return [
            'application' => $application,
            'employee' => $application->employee,
            'balances' => $balances,
            'entitlements' => $entitlements,
            'print' => true,
            'signatureSrc' => $this->signatureSrc($application->employee_signature),
            'supervisor' => $this->stageSummary($application, \App\Enums\LeaveApprovalStage::ImmediateSupervisor),
            'departmentHead' => $this->stageSummary($application, \App\Enums\LeaveApprovalStage::DepartmentHead),
            'adminHead' => $this->stageSummary($application, \App\Enums\LeaveApprovalStage::AdministrativeHead),
            'hrOfficer' => $this->stageSummary($application, \App\Enums\LeaveApprovalStage::HrOfficer),
        ];
    }

    public function signatureSrc(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (str_starts_with($path, 'data:image/')) {
            return $path;
        }

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        $binary = Storage::disk('public')->get($path);
        $mime = str_ends_with($path, '.jpg') || str_ends_with($path, '.jpeg') ? 'image/jpeg' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($binary);
    }

    private function stageSummary(LeaveApplication $application, \App\Enums\LeaveApprovalStage $stage): array
    {
        $rows = $application->assignmentsFor($stage)->values();
        $acted = $rows->first(fn ($row) => in_array($row->status, ['approved', 'denied'], true));
        $name = $acted?->approver_name ?? $rows->pluck('approver_name')->filter()->join(' / ');
        $date = $acted?->acted_at;
        $reason = $acted?->reason ?? $rows->pluck('reason')->filter()->first();
        $decision = $application->stageDecision($stage);
        $signature = $acted?->signature;

        return [
            'name' => $name,
            'date' => $date ? ManilaTime::formatDate($date, 'm/d/Y') : null,
            'reason' => $reason,
            'approved' => $decision === 'approved' || $decision === 'mixed',
            'denied' => $decision === 'denied',
            'mixed' => $decision === 'mixed',
            'signature' => $this->signatureSrc($signature),
            'rows' => $rows,
        ];
    }
}
