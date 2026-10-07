<?php

namespace App\Services\Payroll;

use App\Enums\ApprovalTransactionType;
use App\Enums\LeaveApprovalStage;
use App\Enums\OvertimeRequestStatus;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Services\CentralApprovalWorkflowService;
use App\Support\PayrollSettings;

class OvertimeCentralWorkflowService
{
    public function __construct(private readonly CentralApprovalWorkflowService $central) {}

    public function shouldUseCentralWorkflow(): bool
    {
        return PayrollSettings::overtimeRequiresApproval() && PayrollSettings::overtimeUseCentralApproval();
    }

    public function bootstrapIfNeeded(OvertimeRequest $request): void
    {
        if (! $this->shouldUseCentralWorkflow()) {
            return;
        }

        if ($request->status !== OvertimeRequestStatus::Pending && ! $request->status?->isOpen()) {
            return;
        }

        if ($request->central_approval_config_id && $request->approvalAssignments()->exists()) {
            return;
        }

        $employee = $request->employee ?? Employee::query()->find($request->employee_id);
        if (! $employee) {
            return;
        }

        try {
            $this->central->assertCanSubmit(ApprovalTransactionType::OvertimeRequest);
        } catch (\Throwable) {
            return;
        }

        $config = $this->central->configurationFor(ApprovalTransactionType::OvertimeRequest);
        $meta = $this->central->initialStageMeta($config);
        $status = $this->central->overtimeStatusAfterBootstrap($config);

        $request->update([
            'status' => $status->value,
            'current_approval_stage' => $meta['stage']?->value,
        ]);

        $this->central->bootstrapOvertime($request, $employee);

        if ($meta['stage'] === null) {
            app(OvertimeApprovalService::class)->approve(
                $request->fresh(),
                $employee->user ?? \App\Models\User::query()->where('role', 'admin')->first(),
            );
        }
    }
}
