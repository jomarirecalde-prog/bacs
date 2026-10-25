<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_salary_computations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('payroll_period_id')->nullable()->constrained()->nullOnDelete();
            $table->string('computation_mode', 32);
            $table->string('monthly_subtype', 32)->nullable();
            $table->string('payable_intent', 32)->default('standard');
            $table->date('coverage_start');
            $table->date('coverage_end');
            $table->unsignedSmallInteger('payroll_year')->nullable();
            $table->unsignedTinyInteger('payroll_month')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('salary_type', 32)->nullable();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->decimal('semi_monthly_salary', 12, 2)->nullable();
            $table->decimal('daily_rate', 12, 2)->nullable();
            $table->decimal('hourly_rate', 12, 4)->nullable();
            $table->unsignedSmallInteger('working_hours_per_day')->default(8);
            $table->json('attendance_inputs')->nullable();
            $table->json('system_snapshot')->nullable();
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('premium_pay', 12, 2)->default(0);
            $table->decimal('de_minimis', 12, 2)->default(0);
            $table->decimal('other_earnings', 12, 2)->default(0);
            $table->decimal('gross_wage', 12, 2)->default(0);
            $table->decimal('gross_compensation', 12, 2)->default(0);
            $table->decimal('attendance_deductions', 12, 2)->default(0);
            $table->decimal('statutory_deductions', 12, 2)->default(0);
            $table->decimal('other_deductions', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->json('computation_warnings')->nullable();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('prepared_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'status']);
            $table->index(['coverage_start', 'coverage_end']);
            $table->index(['payroll_period_id', 'employee_id']);
        });

        Schema::create('manual_salary_computation_lines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('computation_id');
            $table->foreign('computation_id', 'msc_lines_computation_fk')
                ->references('id')
                ->on('manual_salary_computations')
                ->cascadeOnDelete();
            $table->string('direction', 16);
            $table->string('category', 64);
            $table->string('code', 64)->nullable();
            $table->string('label');
            $table->decimal('amount', 12, 2)->default(0);
            $table->boolean('is_manual')->default(false);
            $table->boolean('is_system')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['computation_id', 'direction'], 'msc_lines_comp_dir_idx');
        });

        Schema::create('manual_salary_computation_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('computation_id');
            $table->foreign('computation_id', 'msc_overrides_computation_fk')
                ->references('id')
                ->on('manual_salary_computations')
                ->cascadeOnDelete();
            $table->string('field_key', 128);
            $table->decimal('original_value', 12, 4)->nullable();
            $table->decimal('manual_value', 12, 4)->nullable();
            $table->text('reason');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();

            $table->index(['computation_id', 'field_key'], 'msc_overrides_comp_field_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_salary_computation_overrides');
        Schema::dropIfExists('manual_salary_computation_lines');
        Schema::dropIfExists('manual_salary_computations');
    }
};
