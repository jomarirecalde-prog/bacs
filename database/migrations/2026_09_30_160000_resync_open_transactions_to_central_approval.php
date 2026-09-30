<?php

use App\Models\LeaveApplication;
use App\Models\TravelOrder;
use App\Services\CentralApprovalWorkflowService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        /** @var CentralApprovalWorkflowService $workflows */
        $workflows = app(CentralApprovalWorkflowService::class);

        LeaveApplication::query()
            ->whereIn('status', collect(\App\Enums\LeaveStatus::cases())->filter->isOpen()->map->value->all())
            ->orderBy('id')
            ->each(fn (LeaveApplication $application) => $workflows->resyncLeaveApplicationToCentralSettings($application));

        TravelOrder::query()
            ->whereNull('central_approval_config_id')
            ->whereIn('status', collect(\App\Enums\TravelOrderStatus::cases())->filter(fn ($s) => $s->isOpen())->map->value->all())
            ->orderBy('id')
            ->each(fn (TravelOrder $order) => $workflows->resyncTravelOrderToCentralSettings($order));

        TravelOrder::query()
            ->whereNotNull('central_approval_config_id')
            ->whereIn('status', collect(\App\Enums\TravelOrderStatus::cases())->filter(fn ($s) => $s->isOpen())->map->value->all())
            ->whereHas('assignments', fn ($q) => $q->whereIn('stage', [
                \App\Enums\LeaveApprovalStage::DepartmentHead->value,
                \App\Enums\LeaveApprovalStage::AdministrativeHead->value,
            ]))
            ->orderBy('id')
            ->each(fn (TravelOrder $order) => $workflows->resyncTravelOrderToCentralSettings($order));
    }

    public function down(): void
    {
        // Non-reversible data realignment.
    }
};
