<?php

namespace App\Policies;

use App\Models\OvertimeAttachmentReport;
use App\Models\User;

class OvertimeAttachmentReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canViewPayroll() || $user->employee !== null;
    }

    public function view(User $user, OvertimeAttachmentReport $report): bool
    {
        if ($user->canViewPayroll()) {
            return true;
        }

        return $user->employee?->id === $report->employee_id;
    }

    public function reviewPayroll(User $user, OvertimeAttachmentReport $report): bool
    {
        return $user->canViewPayroll() && $report->pendingPayrollReview();
    }

    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    public function update(User $user, OvertimeAttachmentReport $report): bool
    {
        return $user->employee?->id === $report->employee_id && $report->canBeEditedByEmployee();
    }

    public function cancel(User $user, OvertimeAttachmentReport $report): bool
    {
        if (! $report->canBeCancelledByEmployee() && ! $user->isAdmin()) {
            return false;
        }

        return $user->employee?->id === $report->employee_id || $user->isAdmin();
    }

    public function downloadPdf(User $user, OvertimeAttachmentReport $report): bool
    {
        return $this->view($user, $report);
    }
}
