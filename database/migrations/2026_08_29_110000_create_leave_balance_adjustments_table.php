<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_balance_id')->nullable()->constrained('leave_balances')->nullOnDelete();
            $table->string('leave_type_code', 32);
            $table->unsignedSmallInteger('year');
            $table->string('action_type', 48);
            $table->decimal('previous_entitlement', 6, 1)->default(0);
            $table->decimal('new_entitlement', 6, 1)->default(0);
            $table->decimal('previous_balance', 6, 1)->default(0);
            $table->decimal('adjustment_days', 6, 1)->default(0);
            $table->decimal('new_balance', 6, 1)->default(0);
            $table->text('reason')->nullable();
            $table->date('effective_date');
            $table->foreignId('leave_application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('authorized_by_name')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_code', 'year'], 'leave_bal_adj_emp_type_year');
            $table->index(['leave_application_id', 'action_type'], 'leave_bal_adj_app_action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balance_adjustments');
    }
};
