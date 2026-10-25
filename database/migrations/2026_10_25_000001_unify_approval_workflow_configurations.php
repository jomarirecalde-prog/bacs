<?php

use App\Services\CentralApprovalWorkflowService;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(CentralApprovalWorkflowService::class)->synchronizeAllTransactionTypesFromCanonical();
    }

    public function down(): void
    {
        // Irreversible: prior per-transaction differences are not restored.
    }
};
