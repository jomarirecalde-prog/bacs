<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflow_reassignment_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workflow_configuration_id');
            $table->string('transaction_type', 64);
            $table->unsignedBigInteger('department_id');
            $table->string('assignable_type');
            $table->unsignedBigInteger('assignable_id');
            $table->unsignedBigInteger('assignment_id')->nullable();
            $table->string('stage', 64);
            $table->unsignedBigInteger('previous_user_id')->nullable();
            $table->unsignedBigInteger('previous_employee_id')->nullable();
            $table->unsignedBigInteger('new_user_id')->nullable();
            $table->unsignedBigInteger('new_employee_id')->nullable();
            $table->string('previous_assignment_status', 32)->nullable();
            $table->string('new_assignment_status', 32)->nullable();
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('initiated_by');
            $table->timestamps();

            $table->foreign('workflow_configuration_id', 'awf_reassign_cfg_fk')
                ->references('id')->on('approval_workflow_configurations')->cascadeOnDelete();
            $table->foreign('department_id', 'awf_reassign_dept_fk')
                ->references('id')->on('departments')->cascadeOnDelete();
            $table->foreign('previous_user_id', 'awf_reassign_prev_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('previous_employee_id', 'awf_reassign_prev_emp_fk')
                ->references('id')->on('employees')->nullOnDelete();
            $table->foreign('new_user_id', 'awf_reassign_new_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('new_employee_id', 'awf_reassign_new_emp_fk')
                ->references('id')->on('employees')->nullOnDelete();
            $table->foreign('initiated_by', 'awf_reassign_initiator_fk')
                ->references('id')->on('users')->cascadeOnDelete();

            $table->index(['assignable_type', 'assignable_id'], 'awf_reassign_assignable_idx');
            $table->index(['transaction_type', 'department_id', 'created_at'], 'awf_reassign_type_dept_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_workflow_reassignment_logs');
    }
};
