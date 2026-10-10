<?php

namespace Tests\Unit;

use App\Enums\EmployeeSalaryStatus;
use App\Enums\SalaryType;
use App\Models\EmployeeSalaryHistory;
use App\Services\Payroll\EmployeeSalaryService;
use Tests\TestCase;

class EmployeeSalaryGrossCompensationTest extends TestCase
{
    private function service(): EmployeeSalaryService
    {
        return app(EmployeeSalaryService::class);
    }

    public function test_declared_gross_uses_current_de_minimis_when_stored_gross_is_stale(): void
    {
        $salary = new EmployeeSalaryHistory([
            'salary_type' => SalaryType::Monthly,
            'basic_salary' => 24040,
            'gross_compensation' => 14120,
            'monthly_salary' => 24040,
            'semi_monthly_salary' => 12020,
            'status' => EmployeeSalaryStatus::Active,
        ]);

        $this->assertSame(15520.0, $this->service()->grossCompensationPerCutoff($salary, 3500.0));
    }

    public function test_declared_gross_preserves_fixed_allowance_above_de_minimis(): void
    {
        $salary = new EmployeeSalaryHistory([
            'salary_type' => SalaryType::SemiMonthly,
            'basic_salary' => 12000,
            'gross_compensation' => 12500,
            'semi_monthly_salary' => 12000,
            'status' => EmployeeSalaryStatus::Active,
        ]);

        $this->assertSame(12500.0, $this->service()->grossCompensationPerCutoff($salary, 500.0));
        $this->assertSame(12500.0, $this->service()->grossCompensationPerCutoff($salary, 300.0));
        $this->assertSame(12600.0, $this->service()->grossCompensationPerCutoff($salary, 600.0));
    }

    public function test_monthly_gross_compensation_stored_as_monthly_total_is_halved(): void
    {
        $salary = new EmployeeSalaryHistory([
            'salary_type' => SalaryType::Monthly,
            'basic_salary' => 24040,
            'gross_compensation' => 31040,
            'monthly_salary' => 24040,
            'semi_monthly_salary' => 12020,
            'status' => EmployeeSalaryStatus::Active,
        ]);

        $this->assertSame(15520.0, $this->service()->storedGrossCompensationPerCutoff($salary));
        $this->assertSame(15520.0, $this->service()->grossCompensationPerCutoff($salary, 3500.0));
    }
}
