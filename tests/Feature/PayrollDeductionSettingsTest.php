<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\SalaryType;
use App\Models\Attendance;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\AttendanceDeductionService;
use App\Services\Payroll\PayrollPeriodService;
use App\Support\PayrollDeductionConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollDeductionSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixed_per_minute_late_deduction_applies_in_payroll_compute(): void
    {
        PayrollDeductionConfig::appendRevision('2026-01-01', [
            'late' => [
                'enabled' => true,
                'calculation_mode' => 'fixed_per_minute',
                'fixed_per_minute' => 5.0,
                'minimum_billable_minutes' => 0,
                'round_minutes' => 'none',
            ],
        ]);

        $schedule = $this->defaultSchedule();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'ENG',
            'designation_name' => 'Engineer',
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-7777',
            'first_name' => 'Pat',
            'last_name' => 'Late',
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

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-14',
            'am_time_in' => '2026-09-14 08:00:00',
            'am_time_out' => '2026-09-14 12:00:00',
            'pm_time_in' => '2026-09-14 13:00:00',
            'pm_time_out' => '2026-09-14 17:00:00',
            'total_minutes' => 480,
            'late_minutes' => 10,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'late',
        ]);

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Test late fixed',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        app(PayrollPeriodService::class)->computePayroll($period, $admin);

        $row = PayrollEmployee::query()->where('payroll_period_id', $period->id)->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(50.0, (float) $row->late_deduction, 0.01);
    }

    public function test_late_threshold_blocks_deduction_below_minimum_minutes(): void
    {
        $service = app(AttendanceDeductionService::class);

        PayrollDeductionConfig::appendRevision('2026-06-01', [
            'late' => [
                'enabled' => true,
                'calculation_mode' => 'fixed_per_minute',
                'fixed_per_minute' => 5.0,
                'minimum_billable_minutes' => 10,
            ],
        ]);

        $blocked = $service->calculate(0, 9, 0, [
            'daily_rate' => 500,
            'hourly_rate' => 62.5,
            'working_hours' => 8,
        ], '2026-06-01');

        $this->assertSame(0.0, $blocked['late']);

        $applied = $service->calculate(0, 10, 0, [
            'daily_rate' => 500,
            'hourly_rate' => 62.5,
            'working_hours' => 8,
        ], '2026-06-01');

        $this->assertEqualsWithDelta(50.0, $applied['late'], 0.01);
    }

    public function test_admin_can_save_statutory_settings(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.payroll.settings.statutory'), [
            'effective_from' => '2026-10-01',
            'payroll_philhealth_rate' => 2.5,
            'payroll_hdmf_amount' => 100,
            'payroll_auto_philhealth' => '1',
            'payroll_auto_hdmf' => '1',
            'payroll_sss_from_brackets' => '1',
            'payroll_auto_sss' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        $config = PayrollDeductionConfig::forDate('2026-10-15');
        $this->assertTrue($config['statutory']['auto_philhealth']);
        $this->assertSame(2.5, $config['statutory']['philhealth_rate']);
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
