<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\SalaryType;
use App\Models\Attendance;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\PayrollEmployee;
use App\Models\PayrollOvertimeCompensationRate;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\EmployeeSalaryService;
use App\Services\Payroll\PayrollOvertimeCompensationRateService;
use App\Services\Payroll\PayrollPeriodService;
use App\Support\OvertimeMultiplierNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollOvertimeCompensationTest extends TestCase
{
    use RefreshDatabase;

    public function test_multiplier_normalizer_accepts_percentage_and_multiplier(): void
    {
        $fromMultiplier = OvertimeMultiplierNormalizer::normalize('1.25');
        $this->assertSame(1.25, $fromMultiplier['multiplier']);
        $this->assertSame(125.0, $fromMultiplier['percentage']);

        $fromPercent = OvertimeMultiplierNormalizer::normalize('125%');
        $this->assertSame(1.25, $fromPercent['multiplier']);
    }

    public function test_load_default_ot_rates_skips_existing_condition_codes(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PayrollOvertimeCompensationRateService::class);

        $first = $service->loadDefaults('2026-01-01', $admin);
        $this->assertSame(0, $first['inserted']);
        $this->assertSame(21, $first['skipped']);

        PayrollOvertimeCompensationRate::query()->delete();

        $second = $service->loadDefaults('2026-01-01', $admin);
        $this->assertSame(21, $second['inserted']);
        $this->assertSame(0, $second['skipped']);
    }

    public function test_ordinary_day_ot_uses_configured_multiplier_in_payroll(): void
    {
        Setting::put('payroll_ot_use_compensation_matrix', '1');

        $schedule = $this->defaultSchedule();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'OTSTAFF',
            'designation_name' => 'OT Staff',
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-OT01',
            'first_name' => 'Ot',
            'last_name' => 'Tester',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        $salaryService = app(EmployeeSalaryService::class);
        $salaryService->assign($employee, $salaryService->normalizeAmounts([
            'salary_type' => SalaryType::Hourly->value,
            'amount' => 100,
            'effective_from' => '2026-09-01',
        ]), $admin);

        app(PayrollOvertimeCompensationRateService::class)->loadDefaults('2026-01-01', $admin);

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-14',
            'am_time_in' => '2026-09-14 08:00:00',
            'am_time_out' => '2026-09-14 12:00:00',
            'pm_time_in' => '2026-09-14 13:00:00',
            'pm_time_out' => '2026-09-14 19:00:00',
            'total_minutes' => 480,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 120,
            'status' => 'overtime',
        ]);

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Sep OT Test',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        app(PayrollPeriodService::class)->computePayroll($period, $admin);

        $row = PayrollEmployee::query()->where('payroll_period_id', $period->id)->first();
        $this->assertNotNull($row);
        $this->assertEqualsWithDelta(250.0, (float) $row->overtime_pay, 0.01);

        $otEarning = $row->earnings()->where('code', 'overtime')->first();
        $this->assertNotNull($otEarning);
        $this->assertSame('overtime_compensation_matrix', $otEarning->meta['computation_method'] ?? null);
        $this->assertNotEmpty($otEarning->meta['breakdown'] ?? []);
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
