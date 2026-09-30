<?php

use App\Enums\LeaveParallelRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('travel_orders', function (Blueprint $table) {
            $table->id();
            $table->string('travel_order_number', 32)->unique();
            $table->foreignId('requester_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('workflow_id')->nullable()->constrained('leave_approval_workflows')->nullOnDelete();
            $table->unsignedInteger('workflow_version')->nullable();
            $table->string('official_station')->nullable();
            $table->string('number_of_bh', 32)->nullable();
            $table->text('destination')->nullable();
            $table->date('date_start');
            $table->date('date_end');
            $table->text('purpose');
            $table->string('equipment')->nullable();
            $table->string('project_name')->nullable();
            $table->string('client_company')->nullable();
            $table->string('transportation', 64)->nullable();
            $table->string('transportation_other')->nullable();
            $table->string('vehicle_type')->nullable();
            $table->string('plate_number', 32)->nullable();
            $table->text('remarks')->nullable();
            $table->string('status', 32);
            $table->string('current_stage', 32)->nullable();
            $table->string('parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->timestamp('date_requested')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['requester_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['date_start', 'date_end']);
            $table->index('status');
        });

        Schema::create('travel_order_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_order_id')->constrained()->cascadeOnDelete();
            $table->string('destination');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('travel_order_personnel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->unique(['travel_order_id', 'employee_id'], 'travel_order_personnel_unique');
        });

        Schema::create('travel_order_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_order_id')->constrained()->cascadeOnDelete();
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

            $table->unique(['travel_order_id', 'stage', 'user_id'], 'travel_order_assign_unique');
            $table->index(['user_id', 'status']);
            $table->index(['travel_order_id', 'stage']);
        });

        Schema::create('travel_order_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('travel_order_approval_assignments')->nullOnDelete();
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

        Schema::create('travel_order_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_order_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('travel_order_modification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('travel_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('modified_by')->constrained('users')->restrictOnDelete();
            $table->text('reason');
            $table->json('changes');
            $table->timestamps();
        });

        if (Schema::hasTable('app_notifications') && ! Schema::hasColumn('app_notifications', 'travel_order_id')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->foreignId('travel_order_id')->nullable()->after('leave_application_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('app_notifications') && Schema::hasColumn('app_notifications', 'travel_order_id')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->dropConstrainedForeignId('travel_order_id');
            });
        }

        Schema::dropIfExists('travel_order_modification_logs');
        Schema::dropIfExists('travel_order_attachments');
        Schema::dropIfExists('travel_order_approval_actions');
        Schema::dropIfExists('travel_order_approval_assignments');
        Schema::dropIfExists('travel_order_personnel');
        Schema::dropIfExists('travel_order_destinations');
        Schema::dropIfExists('travel_orders');
    }
};
