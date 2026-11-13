<?php

use App\Enums\ApprovalTransactionType;
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
        Schema::create('official_business_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 32)->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('leave_approval_workflows')->nullOnDelete();
            $table->unsignedInteger('workflow_version')->nullable();
            $table->unsignedBigInteger('central_approval_config_id')->nullable();
            $table->foreign('central_approval_config_id', 'ob_req_central_cfg_fk')
                ->references('id')->on('approval_workflow_configurations')->nullOnDelete();
            $table->unsignedInteger('central_approval_config_version')->nullable();
            $table->date('date_filed');
            $table->string('official_station')->nullable();
            $table->text('purpose');
            $table->text('purpose_particular')->nullable();
            $table->string('destination_from')->nullable();
            $table->string('destination_to')->nullable();
            $table->date('date_covered_start');
            $table->date('date_covered_end');
            $table->time('departure_time')->nullable();
            $table->string('transportation_mode', 64)->nullable();
            $table->string('transportation_other')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
            $table->string('vehicle_name')->nullable();
            $table->string('plate_number', 32)->nullable();
            $table->string('accommodation_type')->nullable();
            $table->text('accommodation_particular')->nullable();
            $table->foreignId('trip_ticket_id')->nullable();
            $table->string('status', 32);
            $table->string('current_stage', 32)->nullable();
            $table->string('parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value);
            $table->string('secondary_endorsement_mode', 32)->default(EndorsementMode::Parallel->value);
            $table->string('secondary_parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['date_covered_start', 'date_covered_end'], 'ob_req_cov_dates_idx');
            $table->index('status');
        });

        Schema::create('official_business_participants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('official_business_request_id');
            $table->foreign('official_business_request_id', 'ob_part_req_fk')
                ->references('id')->on('official_business_requests')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('participant_name');
            $table->string('participant_type', 16)->default('employee');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['official_business_request_id', 'sort_order'], 'ob_part_req_sort_idx');
        });

        Schema::create('official_business_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('official_business_request_id');
            $table->foreign('official_business_request_id', 'ob_assign_req_fk')
                ->references('id')->on('official_business_requests')->cascadeOnDelete();
            $table->string('stage', 32);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approver_name');
            $table->string('approver_position')->nullable();
            $table->string('approver_role')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->text('signature')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['official_business_request_id', 'stage', 'user_id'], 'ob_assign_unique');
            $table->index(['user_id', 'status']);
            $table->index(['official_business_request_id', 'stage'], 'ob_assign_req_stage_idx');
        });

        Schema::create('official_business_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('official_business_request_id');
            $table->foreign('official_business_request_id', 'ob_action_req_fk')
                ->references('id')->on('official_business_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->foreign('assignment_id', 'ob_action_assign_fk')
                ->references('id')->on('official_business_approval_assignments')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('stage', 32);
            $table->string('action', 64);
            $table->string('decision', 32)->nullable();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->text('signature')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('acted_at');
        });

        if (! DB::table('approval_workflow_configurations')->where('transaction_type', ApprovalTransactionType::OfficialBusiness->value)->exists()) {
            DB::table('approval_workflow_configurations')->insert([
                'transaction_type' => ApprovalTransactionType::OfficialBusiness->value,
                'endorsement_enabled' => true,
                'final_approval_enabled' => true,
                'version' => 1,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('official_business_approval_actions');
        Schema::dropIfExists('official_business_approval_assignments');
        Schema::dropIfExists('official_business_participants');
        Schema::dropIfExists('official_business_requests');

        DB::table('approval_workflow_configurations')
            ->where('transaction_type', ApprovalTransactionType::OfficialBusiness->value)
            ->delete();
    }
};
