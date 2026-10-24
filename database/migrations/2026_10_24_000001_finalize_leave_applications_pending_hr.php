<?php

use App\Models\LeaveApplication;
use App\Services\LeaveApplicationService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $service = app(LeaveApplicationService::class);

        LeaveApplication::query()
            ->whereIn('status', ['pending_hr', 'partially_approved'])
            ->whereNull('finalized_at')
            ->orderBy('id')
            ->each(fn (LeaveApplication $application) => $service->backfillApproveWithoutHr($application));
    }

    public function down(): void
    {
        // Irreversible: approved leave records, balances, and attendance were created.
    }
};
