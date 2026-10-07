<?php

namespace App\Policies;

use App\Models\OvertimeRequest;
use App\Models\User;
use App\Services\Payroll\OvertimeApprovalService;

class OvertimeRequestPolicy
{
    public function view(User $user, OvertimeRequest $request): bool
    {
        if ($user->canViewPayroll()) {
            return true;
        }

        if ($user->employee?->id === $request->employee_id) {
            return true;
        }

        return $request->approvalAssignments()->where('user_id', $user->id)->exists();
    }

    public function decide(User $user, OvertimeRequest $request): bool
    {
        return app(OvertimeApprovalService::class)->userCanAct($user, $request);
    }
}
