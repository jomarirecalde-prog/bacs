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
        Schema::create('approval_workflow_department_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_configuration_id');
            $table->foreignId('department_id')->constrained()->cascadeOnDelete();
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value);
            $table->string('endorsement_parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->timestamps();

            $table->unique(['workflow_configuration_id', 'department_id'], 'awf_dept_settings_unique');
            $table->foreign('workflow_configuration_id', 'awf_dept_settings_config_fk')
                ->references('id')->on('approval_workflow_configurations')->cascadeOnDelete();
        });

        $configDepartments = DB::table('approval_workflow_assignees')
            ->select('workflow_configuration_id', 'department_id')
            ->distinct()
            ->get();

        foreach ($configDepartments as $row) {
            DB::table('approval_workflow_department_settings')->insert([
                'workflow_configuration_id' => $row->workflow_configuration_id,
                'department_id' => $row->department_id,
                'endorsement_mode' => EndorsementMode::Parallel->value,
                'endorsement_parallel_rule' => LeaveParallelRule::All->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value)->after('parallel_rule');
        });

        Schema::table('travel_orders', function (Blueprint $table) {
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value)->after('parallel_rule');
        });

        Schema::table('official_time_requests', function (Blueprint $table) {
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value)->after('parallel_rule');
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value)->after('current_approval_stage');
            $table->string('endorsement_parallel_rule', 32)->default(LeaveParallelRule::All->value)->after('endorsement_mode');
        });

        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value)->after('current_approval_stage');
            $table->string('endorsement_parallel_rule', 32)->default(LeaveParallelRule::All->value)->after('endorsement_mode');
        });
    }

    public function down(): void
    {
        Schema::table('overtime_requests', function (Blueprint $table) {
            $table->dropColumn(['endorsement_mode', 'endorsement_parallel_rule']);
        });

        Schema::table('attendance_correction_requests', function (Blueprint $table) {
            $table->dropColumn(['endorsement_mode', 'endorsement_parallel_rule']);
        });

        Schema::table('official_time_requests', function (Blueprint $table) {
            $table->dropColumn('endorsement_mode');
        });

        Schema::table('travel_orders', function (Blueprint $table) {
            $table->dropColumn('endorsement_mode');
        });

        Schema::table('leave_applications', function (Blueprint $table) {
            $table->dropColumn('endorsement_mode');
        });

        Schema::dropIfExists('approval_workflow_department_settings');
    }
};
