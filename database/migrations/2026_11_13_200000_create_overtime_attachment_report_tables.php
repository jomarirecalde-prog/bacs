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
        Schema::create('overtime_attachment_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('official_time_request_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('report_no', 32)->unique();
            $table->date('date_coverage_start');
            $table->date('date_coverage_end');
            $table->date('date_submitted')->nullable();
            $table->string('status', 32);
            $table->string('current_stage', 32)->nullable();
            $table->string('parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->string('endorsement_mode', 32)->default(EndorsementMode::Parallel->value);
            $table->string('secondary_endorsement_mode', 32)->default(EndorsementMode::Parallel->value);
            $table->string('secondary_parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->foreignId('central_approval_config_id')->nullable()->constrained('approval_workflow_configurations')->nullOnDelete();
            $table->unsignedInteger('central_approval_config_version')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->text('review_notes')->nullable();
            $table->string('prepared_by')->nullable();
            $table->string('approved_by')->nullable();
            $table->unsignedInteger('total_activity_minutes')->default(0);
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index('report_no');
        });

        Schema::create('overtime_attachment_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attachment_report_id')->constrained('overtime_attachment_reports')->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->text('activity_description');
            $table->date('activity_date');
            $table->time('time_from');
            $table->time('time_to');
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->string('status_remark')->nullable();
            $table->string('reviewed_vouched_by')->nullable();
            $table->foreignId('reviewer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('overtime_attachment_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('overtime_attachment_report_id');
            $table->foreign('overtime_attachment_report_id', 'oar_assign_report_fk')
                ->references('id')->on('overtime_attachment_reports')->cascadeOnDelete();
            $table->string('stage', 32);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('approver_name');
            $table->string('approver_position')->nullable();
            $table->string('approver_role')->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['overtime_attachment_report_id', 'stage', 'user_id'], 'oar_assign_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('overtime_attachment_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('overtime_attachment_report_id');
            $table->foreign('overtime_attachment_report_id', 'oar_action_report_fk')
                ->references('id')->on('overtime_attachment_reports')->cascadeOnDelete();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->foreign('assignment_id', 'oar_action_assign_fk')
                ->references('id')->on('overtime_attachment_approval_assignments')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('stage', 32);
            $table->string('action', 64);
            $table->string('decision', 32)->nullable();
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('acted_at');
        });

        if (! DB::table('approval_workflow_configurations')->where('transaction_type', ApprovalTransactionType::OvertimeAttachmentReport->value)->exists()) {
            DB::table('approval_workflow_configurations')->insert([
                'transaction_type' => ApprovalTransactionType::OvertimeAttachmentReport->value,
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
        Schema::dropIfExists('overtime_attachment_approval_actions');
        Schema::dropIfExists('overtime_attachment_approval_assignments');
        Schema::dropIfExists('overtime_attachment_activities');
        Schema::dropIfExists('overtime_attachment_reports');

        DB::table('approval_workflow_configurations')
            ->where('transaction_type', ApprovalTransactionType::OvertimeAttachmentReport->value)
            ->delete();
    }
};
