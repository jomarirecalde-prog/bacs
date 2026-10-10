<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\SalaryType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeDeduction;
use App\Models\PayrollDeductionType;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\EmployeeSalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePayrollDeclaredDataUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_recurring_deduction_and_salary_history_can_be_updated_in_place(): void
    {
        $schedule = $this->defaultSchedule();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'TEST',
            'designation_name' => 'Test',
        ]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-0002',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        $sssType = PayrollDeductionType::query()->where('code', 'sss')->first();
        $deduction = EmployeeDeduction::query()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $sssType->id,
            'amount' => 495,
            'frequency' => 'semi_monthly',
            'effective_from' => '2026-01-01',
            'is_active' => true,
        ]);

        $svc = app(EmployeeSalaryService::class);
        $data = $svc->normalizeAmounts([
            'salary_type' => SalaryType::SemiMonthly->value,
            'basic_salary' => 10000,
            'gross_compensation' => 10200,
            'amount' => 10000,
            'effective_from' => '2026-01-01',
        ]);
        $data['gross_compensation'] = 10200;
        $history = $svc->assign($employee, $data, $admin);

        $this->actingAs($admin)->put(route('admin.payroll.employees.deductions.update', [$employee, $deduction]), [
            'amount' => 550,
            'effective_from' => '2026-01-01',
        ])->assertRedirect();

        $this->assertSame('550.00', (string) $deduction->fresh()->amount);

        $this->actingAs($admin)->put(route('admin.payroll.employees.salary.update', [$employee, $history]), [
            'salary_type' => SalaryType::SemiMonthly->value,
            'basic_salary' => 12000,
            'gross_compensation' => 12500,
        ])->assertRedirect();

        $fresh = $history->fresh();
        $this->assertSame('12000.00', (string) $fresh->basic_salary);
        $this->assertSame('12000.00', (string) $fresh->semi_monthly_salary);
        $this->assertSame('12500.00', (string) $fresh->gross_compensation);
    }

    private function defaultSchedule(): WorkSchedule
    {
        return WorkSchedule::query()->create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => AccountStatus::Active,
        ]);
    }
}
