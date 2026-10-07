<?php

use App\Enums\ApprovalTransactionType;
use App\Enums\LeaveParallelRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('official_time_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('requires_location')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('official_time_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 32)->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('designation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('official_time_type_id')->constrained()->restrictOnDelete();
            $table->date('date');
            $table->time('time_from');
            $table->time('time_to');
            $table->unsignedInteger('duration_minutes');
            $table->text('purpose');
            $table->text('activity')->nullable();
            $table->string('location')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->string('status', 32);
            $table->string('current_stage', 32)->nullable();
            $table->string('parallel_rule', 32)->default(LeaveParallelRule::All->value);
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
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['date', 'status']);
            $table->index('request_no');
        });

        Schema::create('official_time_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('official_time_request_id');
            $table->foreign('official_time_request_id', 'ot_assign_request_fk')
                ->references('id')->on('official_time_requests')->cascadeOnDelete();
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

            $table->unique(['official_time_request_id', 'stage', 'user_id'], 'official_time_assign_unique');
            $table->index(['user_id', 'status']);
        });

        Schema::create('official_time_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('official_time_request_id');
            $table->foreign('official_time_request_id', 'ot_action_request_fk')
                ->references('id')->on('official_time_requests')->cascadeOnDelete();
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->foreign('assignment_id', 'ot_action_assign_fk')
                ->references('id')->on('official_time_approval_assignments')->nullOnDelete();
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

        if (Schema::hasTable('app_notifications') && ! Schema::hasColumn('app_notifications', 'official_time_request_id')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->unsignedBigInteger('official_time_request_id')->nullable()->after('travel_order_id');
                $table->foreign('official_time_request_id', 'app_notif_ot_req_fk')
                    ->references('id')->on('official_time_requests')->nullOnDelete();
            });
        }

        if (! DB::table('approval_workflow_configurations')->where('transaction_type', ApprovalTransactionType::OfficialTime->value)->exists()) {
            DB::table('approval_workflow_configurations')->insert([
                'transaction_type' => ApprovalTransactionType::OfficialTime->value,
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
        if (Schema::hasTable('app_notifications') && Schema::hasColumn('app_notifications', 'official_time_request_id')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->dropConstrainedForeignId('official_time_request_id');
            });
        }

        Schema::dropIfExists('official_time_approval_actions');
        Schema::dropIfExists('official_time_approval_assignments');
        Schema::dropIfExists('official_time_requests');
        Schema::dropIfExists('official_time_types');

        DB::table('approval_workflow_configurations')
            ->where('transaction_type', ApprovalTransactionType::OfficialTime->value)
            ->delete();
    }
};
