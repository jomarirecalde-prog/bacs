<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('approval_workflow_configurations', 'secondary_endorsement_enabled')) {
            return;
        }

        Schema::table('approval_workflow_configurations', function (Blueprint $table) {
            $table->boolean('secondary_endorsement_enabled')->default(false)->after('endorsement_enabled');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('approval_workflow_configurations', 'secondary_endorsement_enabled')) {
            return;
        }

        Schema::table('approval_workflow_configurations', function (Blueprint $table) {
            $table->dropColumn('secondary_endorsement_enabled');
        });
    }
};
