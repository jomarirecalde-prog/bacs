<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\Employee;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_can_view_my_salary_index(): void
    {
        [$employee] = $this->payrollRow(PayrollPeriodStatus::Computed);

        $this->actingAs($employee->user)
            ->get(route('employee.salary.index'))
            ->assertOk()
            ->assertSee('My Salary')
            ->assertSee('Estimated net salary');
    }

    public function test_employee_can_view_own_unfinalized_salary_detail(): void
    {
        [$employee, $row] = $this->payrollRow(PayrollPeriodStatus::ForReview);

        $this->actingAs($employee->user)
            ->get(route('employee.salary.show', $row))
            ->assertOk()
            ->assertSee('Estimated net salary');
    }

    public function test_employee_cannot_view_other_salary_detail(): void
    {
        [, $row] = $this->payrollRow(PayrollPeriodStatus::Computed);
        $other = $this->otherEmployee();

        $this->actingAs($other->user)
            ->get(route('employee.salary.show', $row))
            ->assertForbidden();
    }

    public function test_employee_still_cannot_view_unfinalized_payslip_route(): void
    {
        [$employee, $row] = $this->payrollRow(PayrollPeriodStatus::Computed);

        $this->actingAs($employee->user)
            ->get(route('employee.payroll.show', $row))
            ->assertForbidden();
    }

    public function test_salary_history_filter_by_released_status(): void
    {
        [$employee, $draftRow] = $this->payrollRow(PayrollPeriodStatus::Draft, 'BACS-2026-8801', '2026-08-01', '2026-08-15');
        [, $releasedRow] = $this->payrollRow(PayrollPeriodStatus::Finalized, 'BACS-2026-8801', '2026-09-01', '2026-09-15', $employee);

        $this->actingAs($employee->user)
            ->get(route('employee.salary.index', ['status' => 'released']))
            ->assertOk()
            ->assertSee($releasedRow->payrollPeriod->period_name)
            ->assertDontSee($draftRow->payrollPeriod->period_name);
    }

    /** @return array{0: Employee, 1: PayrollEmployee} */
    private function payrollRow(
        PayrollPeriodStatus $status,
        string $employeeNumber = 'BACS-2026-7780',
        string $start = '2026-09-11',
        string $end = '2026-09-25',
        ?Employee $employee = null,
    ): array {
        $schedule = WorkSchedule::query()->create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => AccountStatus::Active,
        ]);

        if (! $employee) {
            $user = User::factory()->create();
            $employee = Employee::query()->create([
                'user_id' => $user->id,
                'employee_number' => $employeeNumber,
                'first_name' => 'Salary',
                'last_name' => 'Tester',
                'email' => $user->email,
                'employment_status' => EmploymentStatus::Regular,
                'work_schedule_id' => $schedule->id,
            ]);
        }

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Period '.$start,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
        ]);

        $row = PayrollEmployee::query()->create([
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'employee_name' => $employee->fullName(),
            'net_pay' => 5000,
            'gross_compensation' => 5500,
            'gross_wage' => 5000,
            'total_basic_pay' => 5000,
            'basic_pay' => 5000,
            'total_deductions' => 500,
        ]);

        return [$employee, $row];
    }

    private function otherEmployee(): Employee
    {
        $schedule = WorkSchedule::query()->firstOrCreate(
            ['name' => 'Regular-Other'],
            [
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'grace_period_minutes' => 10,
                'required_minutes' => 480,
                'work_days' => [1, 2, 3, 4, 5],
                'is_default' => false,
                'status' => AccountStatus::Active,
            ],
        );

        $user = User::factory()->create();
        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-7799',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
        ]);
    }
}
