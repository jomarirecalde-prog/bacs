<?php

use App\Enums\EndorsementMode;
use App\Enums\LeaveParallelRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_workflow_department_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('approval_workflow_department_settings', 'secondary_endorsement_mode')) {
                $table->string('secondary_endorsement_mode', 32)
                    ->default(EndorsementMode::Parallel->value)
                    ->after('endorsement_parallel_rule');
            }
            if (! Schema::hasColumn('approval_workflow_department_settings', 'secondary_endorsement_parallel_rule')) {
                $table->string('secondary_endorsement_parallel_rule', 32)
                    ->default(LeaveParallelRule::All->value)
                    ->after('secondary_endorsement_mode');
            }
        });

        DB::table('approval_workflow_department_settings')->update([
            'secondary_endorsement_mode' => DB::raw('endorsement_mode'),
            'secondary_endorsement_parallel_rule' => DB::raw('endorsement_parallel_rule'),
        ]);

        Schema::table('leave_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('leave_applications', 'secondary_endorsement_mode')) {
                $table->string('secondary_endorsement_mode', 32)
                    ->default(EndorsementMode::Parallel->value)
                    ->after('endorsement_mode');
            }
            if (! Schema::hasColumn('leave_applications', 'secondary_parallel_rule')) {
                $table->string('secondary_parallel_rule', 32)
                    ->default(LeaveParallelRule::All->value)
                    ->after('secondary_endorsement_mode');
            }
        });

        Schema::table('travel_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('travel_orders', 'secondary_endorsement_mode')) {
                $table->string('secondary_endorsement_mode', 32)
                    ->default(EndorsementMode::Parallel->value)
                    ->after('endorsement_mode');
            }
            if (! Schema::hasColumn('travel_orders', 'secondary_parallel_rule')) {
                $table->string('secondary_parallel_rule', 32)
                    ->default(LeaveParallelRule::All->value)
                    ->after('secondary_endorsement_mode');
            }
        });

        Schema::table('official_time_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('official_time_requests', 'secondary_endorsement_mode')) {
                $table->string('secondary_endorsement_mode', 32)
                    ->nullable()
                    ->after('parallel_rule');
            }
            if (! Schema::hasColumn('official_time_requests', 'secondary_parallel_rule')) {
                $table->string('secondary_parallel_rule', 32)
                    ->nullable()
                    ->after('secondary_endorsement_mode');
            }
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('attendance_correction_requests', 'secondary_endorsement_mode')) {
                $table->string('secondary_endorsement_mode', 32)
                    ->default(EndorsementMode::Parallel->value)
                    ->after('endorsement_parallel_rule');
            }
            if (! Schema::hasColumn('attendance_correction_requests', 'secondary_endorsement_parallel_rule')) {
                $table->string('secondary_endorsement_parallel_rule', 32)
                    ->default(LeaveParallelRule::All->value)
                    ->after('secondary_endorsement_mode');
            }
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('overtime_requests', 'secondary_endorsement_mode')) {
                $table->string('secondary_endorsement_mode', 32)
                    ->default(EndorsementMode::Parallel->value)
                    ->after('endorsement_parallel_rule');
            }
            if (! Schema::hasColumn('overtime_requests', 'secondary_endorsement_parallel_rule')) {
                $table->string('secondary_endorsement_parallel_rule', 32)
                    ->default(LeaveParallelRule::All->value)
                    ->after('secondary_endorsement_mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropColumn(['secondary_endorsement_mode', 'secondary_endorsement_parallel_rule']);
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->dropColumn(['secondary_endorsement_mode', 'secondary_endorsement_parallel_rule']);
        });

        Schema::table('official_time_requests', function (Blueprint $table) {
            $table->dropColumn(['secondary_endorsement_mode', 'secondary_parallel_rule']);
        });

        Schema::table('travel_orders', function (Blueprint $table) {
            $table->dropColumn(['secondary_endorsement_mode', 'secondary_parallel_rule']);
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn(['secondary_endorsement_mode', 'secondary_parallel_rule']);
        });

        Schema::table('approval_workflow_department_settings', function (Blueprint $table) {
            $table->dropColumn(['secondary_endorsement_mode', 'secondary_endorsement_parallel_rule']);
        });
    }
};
