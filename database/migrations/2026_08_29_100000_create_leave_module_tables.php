<?php

use App\Enums\LeaveParallelRule;
use App\Enums\LeaveType;
use App\Enums\SpecialLeaveType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('entitlement_days')->default(0);
            $table->string('category', 32)->default('standard');
            $table->boolean('is_special')->default(false);
            $table->boolean('counts_calendar_days')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_approval_workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('department_id');
        });

        Schema::create('leave_approval_workflow_approvers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('leave_approval_workflows')->cascadeOnDelete();
            $table->string('stage', 32);
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workflow_id', 'stage', 'user_id'], 'leave_wf_approver_unique');
            $table->index(['user_id', 'stage']);
        });

        Schema::create('leave_applications', function (Blueprint $table) {
            $table->id();
            $table->string('application_number', 32)->unique();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('leave_type', 32);
            $table->string('special_leave_type', 32)->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('requested_days', 6, 1);
            $table->text('reason');
            $table->string('employee_print_name');
            $table->text('employee_signature')->nullable();
            $table->boolean('declaration_accepted')->default(false);
            $table->timestamp('date_filed');
            $table->string('status', 32);
            $table->string('current_stage', 32)->nullable();
            $table->string('parallel_rule', 32)->default(LeaveParallelRule::All->value);
            $table->string('payment_type', 32)->nullable();
            $table->date('hr_sil_as_of')->nullable();
            $table->decimal('hr_sil_balance', 6, 1)->nullable();
            $table->decimal('hr_leave_taken', 6, 1)->nullable();
            $table->decimal('hr_leave_balance', 6, 1)->nullable();
            $table->text('hr_remarks')->nullable();
            $table->boolean('attendance_conflict')->default(false);
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->foreignId('leave_id')->nullable()->constrained('leaves')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['department_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');
        });

        Schema::create('leave_approval_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
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

            $table->unique(['leave_application_id', 'stage', 'user_id'], 'leave_assign_unique');
            $table->index(['user_id', 'status']);
            $table->index(['leave_application_id', 'stage']);
        });

        Schema::create('leave_approval_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('leave_approval_assignments')->nullOnDelete();
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

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('leave_type_code', 32);
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled_days', 6, 1)->default(0);
            $table->decimal('used_days', 6, 1)->default(0);
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_code', 'year'], 'leave_balance_unique');
        });

        Schema::create('leave_attendance_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendance')->nullOnDelete();
            $table->date('attendance_date');
            $table->dateTime('time_in')->nullable();
            $table->dateTime('time_out')->nullable();
            $table->string('attendance_status', 32)->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['leave_application_id', 'attendance_date'], 'leave_att_conflict_idx');
        });

        Schema::table('app_notifications', function (Blueprint $table) {
            $table->foreignId('leave_application_id')->nullable()->after('calendar_event_id')->constrained()->nullOnDelete();
        });

        $this->seedLeaveTypes();
        $this->seedDefaultWorkflow();
    }

    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('leave_application_id');
        });

        Schema::dropIfExists('leave_attendance_conflicts');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_approval_actions');
        Schema::dropIfExists('leave_approval_assignments');
        Schema::dropIfExists('leave_applications');
        Schema::dropIfExists('leave_approval_workflow_approvers');
        Schema::dropIfExists('leave_approval_workflows');
        Schema::dropIfExists('leave_types');
    }

    private function seedLeaveTypes(): void
    {
        $now = now();
        $rows = [];

        foreach (LeaveType::cases() as $index => $type) {
            $rows[] = [
                'code' => $type->value,
                'name' => $type->label(),
                'entitlement_days' => $type->defaultDays(),
                'category' => 'standard',
                'is_special' => $type === LeaveType::Special,
                'counts_calendar_days' => $type->countsCalendarDays(),
                'sort_order' => $index + 1,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (SpecialLeaveType::cases() as $index => $type) {
            $rows[] = [
                'code' => $type->value,
                'name' => $type->label(),
                'entitlement_days' => $type->defaultDays(),
                'category' => 'special',
                'is_special' => true,
                'counts_calendar_days' => true,
                'sort_order' => 20 + $index,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('leave_types')->insert($rows);
    }

    private function seedDefaultWorkflow(): void
    {
        DB::table('leave_approval_workflows')->insert([
            'department_id' => null,
            'name' => 'Company Default',
            'parallel_rule' => LeaveParallelRule::All->value,
            'is_default' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
