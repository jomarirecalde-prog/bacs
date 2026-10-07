<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained('attendance')->nullOnDelete();
            $table->date('attendance_date');
            $table->unsignedInteger('recorded_minutes')->default(0);
            $table->unsignedInteger('approved_minutes')->default(0);
            $table->string('status', 32)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'attendance_date']);
            $table->index(['status', 'attendance_date']);
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_payroll_period_id')->nullable()->constrained('payroll_periods')->nullOnDelete();
            $table->foreignId('target_payroll_period_id')->constrained('payroll_periods')->restrictOnDelete();
            $table->string('adjustment_type', 64);
            $table->decimal('original_amount', 12, 2)->nullable();
            $table->decimal('corrected_amount', 12, 2)->nullable();
            $table->decimal('adjustment_amount', 12, 2);
            $table->string('direction', 16)->default('earning');
            $table->text('reason');
            $table->string('reference_type', 64)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32)->default('approved');
            $table->timestamps();

            $table->index(['target_payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('overtime_requests');
    }
};
