<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\SalaryType;
use App\Models\Attendance;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\PayrollPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollPremiumTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_holiday_work_adds_holiday_pay_in_premium_only_mode(): void
    {
        $schedule = $this->defaultSchedule();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'STAFF',
            'designation_name' => 'Staff',
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-7777',
            'first_name' => 'Holiday',
            'last_name' => 'Worker',
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

        Holiday::query()->create([
            'name' => 'Test Regular Holiday',
            'holiday_date' => '2026-09-14',
            'type' => 'regular',
        ]);

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-14',
            'am_time_in' => '2026-09-14 08:00:00',
            'am_time_out' => '2026-09-14 12:00:00',
            'pm_time_in' => '2026-09-14 13:00:00',
            'pm_time_out' => '2026-09-14 17:00:00',
            'total_minutes' => 480,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => 'present',
        ]);

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Sep 11-25 Premium',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        app(PayrollPeriodService::class)->computePayroll($period, $admin);

        $row = PayrollEmployee::query()->where('payroll_period_id', $period->id)->first();
        $this->assertNotNull($row);
        $this->assertGreaterThan(0, (float) $row->holiday_pay);
        $this->assertGreaterThan((float) $row->total_basic_pay, (float) $row->gross_wage);
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
