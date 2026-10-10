<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\SalaryType;
use App\Models\Attendance;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\EmployeeBenefit;
use App\Models\EmployeeDeduction;
use App\Models\PayrollDeductionType;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\PayrollPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_semi_monthly_payroll_applies_alu_and_deductions(): void
    {
        $schedule = $this->defaultSchedule();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'JUNIOR_ENGR',
            'designation_name' => 'Junior Office Engineer',
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-8888',
            'first_name' => 'Jun',
            'last_name' => 'Engineer',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        $this->actingAs($admin)->post(route('admin.payroll.employees.salary.store', $employee), [
            'salary_type' => SalaryType::SemiMonthly->value,
            'amount' => 10000,
            'effective_from' => '2026-09-01',
        ])->assertRedirect();

        EmployeeBenefit::query()->create([
            'employee_id' => $employee->id,
            'amount' => 1000,
            'effective_from' => '2026-09-01',
            'is_active' => true,
        ]);

        $sssType = PayrollDeductionType::query()->where('code', 'sss')->first();
        EmployeeDeduction::query()->create([
            'employee_id' => $employee->id,
            'deduction_type_id' => $sssType->id,
            'amount' => 500,
            'effective_from' => '2026-09-01',
            'is_active' => true,
        ]);

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-14',
            'am_time_in' => '2026-09-14 08:00:00',
            'am_time_out' => '2026-09-14 12:00:00',
            'pm_time_in' => '2026-09-14 13:00:00',
            'pm_time_out' => '2026-09-14 17:00:00',
            'total_minutes' => 480,
            'late_minutes' => 30,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'late',
        ]);

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Test Sep 11-25',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        $result = app(PayrollPeriodService::class)->computePayroll($period, $admin);
        $this->assertSame(1, $result['employees']);

        $this->assertDatabaseHas('payroll_attendance_summary', [
            'employee_id' => $employee->id,
            'late_minutes' => 30,
        ]);

        $row = PayrollEmployee::query()->where('payroll_period_id', $period->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('10000.00', (string) $row->basic_pay);
        $this->assertGreaterThan(0, (float) $row->late_deduction);
        $this->assertGreaterThan(0, (float) $row->computed_total_basic_pay);
        $this->assertFalse($row->total_basic_pay_manually_set);
        $this->assertEqualsWithDelta(
            (float) $row->computed_total_basic_pay,
            (float) $row->total_basic_pay,
            0.01,
        );

        $suggestedTotal = (float) $row->computed_total_basic_pay;

        $this->actingAs($admin)->put(route('admin.payroll.employees.payroll.total-basic-pay', $row), [
            'total_basic_pay' => $suggestedTotal,
        ])->assertRedirect();

        $row->refresh();
        $this->assertTrue($row->total_basic_pay_manually_set);
        $this->assertSame('1000.00', (string) $row->de_minimis);
        $this->assertEqualsWithDelta(
            round((float) $row->gross_compensation - 500, 2),
            (float) $row->net_pay,
            0.01
        );
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
