<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_earning_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('payroll_deduction_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_statutory')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('employee_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->constrained('payroll_deduction_types')->restrictOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('frequency', 32)->default('semi_monthly');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
        });

        Schema::create('employee_benefits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('benefit_code', 64)->default('de_minimis');
            $table->string('label')->default('De Minimis');
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('frequency', 32)->default('semi_monthly');
            $table->date('effective_from')->nullable();
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['employee_id', 'is_active']);
        });

        Schema::create('payroll_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('employee_number');
            $table->string('employee_name');
            $table->foreignId('department_id')->nullable();
            $table->string('department_name')->nullable();
            $table->foreignId('designation_id')->nullable();
            $table->string('designation_name')->nullable();
            $table->string('salary_type', 32)->nullable();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->decimal('monthly_salary', 12, 2)->nullable();
            $table->decimal('semi_monthly_salary', 12, 2)->nullable();
            $table->decimal('daily_rate', 12, 2)->nullable();
            $table->decimal('hourly_rate', 12, 4)->nullable();
            $table->unsignedSmallInteger('working_hours_per_day')->default(8);
            $table->decimal('basic_pay', 12, 2)->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->decimal('late_deduction', 12, 2)->default(0);
            $table->decimal('undertime_deduction', 12, 2)->default(0);
            $table->decimal('total_basic_pay', 12, 2)->default(0);
            $table->decimal('overtime_pay', 12, 2)->default(0);
            $table->decimal('holiday_pay', 12, 2)->default(0);
            $table->decimal('premium_pay', 12, 2)->default(0);
            $table->decimal('gross_wage', 12, 2)->default(0);
            $table->decimal('de_minimis', 12, 2)->default(0);
            $table->decimal('other_earnings', 12, 2)->default(0);
            $table->decimal('gross_compensation', 12, 2)->default(0);
            $table->decimal('total_deductions', 12, 2)->default(0);
            $table->decimal('net_pay', 12, 2)->default(0);
            $table->string('computation_status', 32)->default('ok');
            $table->json('computation_warnings')->nullable();
            $table->timestamp('computed_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_period_id', 'employee_id']);
            $table->index(['payroll_period_id', 'designation_id']);
        });

        Schema::create('payroll_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('earning_type_id')->nullable()->constrained('payroll_earning_types')->nullOnDelete();
            $table->string('code', 64);
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['payroll_employee_id', 'code']);
        });

        Schema::create('payroll_deductions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('deduction_type_id')->nullable()->constrained('payroll_deduction_types')->nullOnDelete();
            $table->string('code', 64);
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['payroll_employee_id', 'code']);
        });

        $this->seedTypes();
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_deductions');
        Schema::dropIfExists('payroll_earnings');
        Schema::dropIfExists('payroll_employees');
        Schema::dropIfExists('employee_benefits');
        Schema::dropIfExists('employee_deductions');
        Schema::dropIfExists('payroll_deduction_types');
        Schema::dropIfExists('payroll_earning_types');
    }

    private function seedTypes(): void
    {
        $now = now();
        $earnings = [
            ['code' => 'basic_pay', 'name' => 'Basic Pay', 'sort_order' => 10],
            ['code' => 'overtime', 'name' => 'Overtime', 'sort_order' => 20],
            ['code' => 'holiday_pay', 'name' => 'Holiday Pay', 'sort_order' => 30],
            ['code' => 'premium_pay', 'name' => 'Premium Pay', 'sort_order' => 40],
            ['code' => 'de_minimis', 'name' => 'De Minimis', 'sort_order' => 50],
            ['code' => 'other_earnings', 'name' => 'Other Earnings', 'sort_order' => 60],
        ];

        foreach ($earnings as $row) {
            DB::table('payroll_earning_types')->insert($row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        $deductions = [
            ['code' => 'absence', 'name' => 'Absence', 'sort_order' => 5, 'is_statutory' => false],
            ['code' => 'late', 'name' => 'Late', 'sort_order' => 6, 'is_statutory' => false],
            ['code' => 'undertime', 'name' => 'Undertime', 'sort_order' => 7, 'is_statutory' => false],
            ['code' => 'sss', 'name' => 'SSS', 'sort_order' => 10, 'is_statutory' => true],
            ['code' => 'philhealth', 'name' => 'PhilHealth', 'sort_order' => 20, 'is_statutory' => true],
            ['code' => 'hdmf', 'name' => 'HDMF / Pag-IBIG', 'sort_order' => 30, 'is_statutory' => true],
            ['code' => 'tax', 'name' => 'Income Tax', 'sort_order' => 40, 'is_statutory' => true],
            ['code' => 'sss_loan', 'name' => 'SSS Loan', 'sort_order' => 50, 'is_statutory' => false],
            ['code' => 'hdmf_loan', 'name' => 'Pag-IBIG Loan', 'sort_order' => 60, 'is_statutory' => false],
            ['code' => 'rcbc_loan', 'name' => 'RCBC Loan', 'sort_order' => 70, 'is_statutory' => false],
            ['code' => 'phone_loan', 'name' => 'Phone Loan', 'sort_order' => 80, 'is_statutory' => false],
            ['code' => 'medical_pooling', 'name' => 'Medical Pooling', 'sort_order' => 90, 'is_statutory' => false],
            ['code' => 'cash_advance', 'name' => 'Cash Advance', 'sort_order' => 100, 'is_statutory' => false],
            ['code' => 'other_deduction', 'name' => 'Other Deduction', 'sort_order' => 110, 'is_statutory' => false],
        ];

        foreach ($deductions as $row) {
            DB::table('payroll_deduction_types')->insert($row + ['is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
};
