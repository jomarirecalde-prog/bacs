<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\PayrollComputationStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\SalaryType;
use App\Models\Attendance;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\EmployeeSalaryService;
use App\Services\Payroll\PayrollPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollOvertimePayrollIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_payroll_compute_includes_matrix_ot_in_gross_and_allows_finalize(): void
    {
        Setting::put('payroll_ot_use_compensation_matrix', '1');

        $schedule = $this->defaultSchedule();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'PAYINT',
            'designation_name' => 'Payroll Integration',
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-PAYINT',
            'first_name' => 'Pay',
            'last_name' => 'Integration',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        $salaryService = app(EmployeeSalaryService::class);
        $salaryService->assign($employee, $salaryService->normalizeAmounts([
            'salary_type' => SalaryType::SemiMonthly->value,
            'amount' => 10000,
            'effective_from' => '2026-09-01',
        ]), $admin);

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-09-14',
            'am_time_in' => '2026-09-14 08:00:00',
            'am_time_out' => '2026-09-14 12:00:00',
            'pm_time_in' => '2026-09-14 13:00:00',
            'pm_time_out' => '2026-09-14 19:00:00',
            'total_minutes' => 480,
            'overtime_minutes' => 120,
            'status' => 'overtime',
        ]);

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Integration Sep',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => PayrollPeriodStatus::Draft,
            'created_by' => $admin->id,
        ]);

        app(PayrollPeriodService::class)->computePayroll($period, $admin);

        $row = PayrollEmployee::query()->where('payroll_period_id', $period->id)->first();
        $this->assertNotNull($row);
        $this->assertSame(PayrollComputationStatus::Ok, $row->computation_status);
        $this->assertGreaterThan(0, (float) $row->overtime_pay);
        $this->assertGreaterThan((float) $row->total_basic_pay, (float) $row->gross_wage);

        $otLine = $row->earnings()->where('code', 'overtime')->first();
        $this->assertNotNull($otLine);
        $this->assertSame('overtime_compensation_matrix', $otLine->meta['computation_method'] ?? null);
        $this->assertNotEmpty($otLine->meta['breakdown'] ?? []);

        $this->actingAs($admin)->put(route('admin.payroll.employees.payroll.total-basic-pay', $row), [
            'total_basic_pay' => (float) $row->computed_total_basic_pay,
        ])->assertRedirect();

        $service = app(PayrollPeriodService::class);
        $period->refresh();
        $service->transition($period, PayrollPeriodStatus::ForReview, $admin);
        $service->transition($period->fresh(), PayrollPeriodStatus::Approved, $admin);
        $final = $service->transition($period->fresh(), PayrollPeriodStatus::Finalized, $admin, [
            'acknowledge_warnings' => true,
        ]);

        $this->assertSame(PayrollPeriodStatus::Finalized, $final->status);
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
