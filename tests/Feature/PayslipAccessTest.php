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

class PayslipAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_view_unfinalized_payslip(): void
    {
        [$employee, $row] = $this->payrollRow(PayrollPeriodStatus::Computed);

        $this->actingAs($employee->user)
            ->get(route('employee.payroll.show', $row))
            ->assertForbidden();
    }

    public function test_employee_can_view_finalized_own_payslip(): void
    {
        [$employee, $row] = $this->payrollRow(PayrollPeriodStatus::Finalized);

        $this->actingAs($employee->user)
            ->get(route('employee.payroll.show', $row))
            ->assertOk();
    }

    public function test_employee_cannot_view_other_payslip(): void
    {
        [, $row] = $this->payrollRow(PayrollPeriodStatus::Finalized);
        $other = User::factory()->create();
        $otherEmployee = Employee::query()->create([
            'user_id' => $other->id,
            'employee_number' => 'BACS-2026-7777',
            'first_name' => 'Other',
            'last_name' => 'Person',
            'email' => $other->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => WorkSchedule::query()->create([
                'name' => 'R', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
                'grace_period_minutes' => 10, 'required_minutes' => 480, 'work_days' => [1, 2, 3, 4, 5],
                'is_default' => true, 'status' => AccountStatus::Active,
            ])->id,
        ]);

        $this->actingAs($otherEmployee->user)
            ->get(route('employee.payroll.show', $row))
            ->assertForbidden();
    }

    /** @return array{0: Employee, 1: PayrollEmployee} */
    private function payrollRow(PayrollPeriodStatus $status): array
    {
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

        $user = User::factory()->create();
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-7770',
            'first_name' => 'Payslip',
            'last_name' => 'Tester',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
        ]);

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Test',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
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
        ]);

        return [$employee, $row];
    }
}
