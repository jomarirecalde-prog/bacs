<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\SalaryType;
use App\Models\Attendance;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\PayrollPeriodService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayrollFinalizeValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_finalize_without_approved_status_or_payroll_rows(): void
    {
        $admin = User::factory()->admin()->create();
        $period = PayrollPeriod::query()->create([
            'period_name' => 'Empty',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => PayrollPeriodStatus::Computed,
            'created_by' => $admin->id,
        ]);

        $service = app(PayrollPeriodService::class);

        try {
            $service->transition($period, PayrollPeriodStatus::Finalized, $admin);
            $this->fail('Expected validation exception.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }
    }

    public function test_finalized_period_can_be_marked_paid(): void
    {
        $admin = User::factory()->admin()->create();
        $period = PayrollPeriod::query()->create([
            'period_name' => 'Paid test',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => PayrollPeriodStatus::Finalized,
            'created_by' => $admin->id,
        ]);

        $updated = app(PayrollPeriodService::class)->transition($period, PayrollPeriodStatus::Paid, $admin);

        $this->assertSame(PayrollPeriodStatus::Paid, $updated->status);
    }

    public function test_finalize_succeeds_when_approved_and_payroll_computed(): void
    {
        $schedule = WorkSchedule::query()->create([
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

        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'STAFF2',
            'designation_name' => 'Staff',
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-6666',
            'first_name' => 'Pay',
            'last_name' => 'Roll',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        $this->actingAs($admin)->post(route('admin.payroll.employees.salary.store', $employee), [
            'salary_type' => SalaryType::SemiMonthly->value,
            'amount' => 10000,
            'effective_from' => '2026-09-01',
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
            'period_name' => 'Finalize me',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => PayrollPeriodStatus::Draft,
            'created_by' => $admin->id,
        ]);

        $service = app(PayrollPeriodService::class);
        $service->computePayroll($period, $admin);
        $period->refresh();

        $payrollRow = PayrollEmployee::query()->where('payroll_period_id', $period->id)->first();
        $this->assertNotNull($payrollRow);
        $this->actingAs($admin)->put(route('admin.payroll.employees.payroll.total-basic-pay', $payrollRow), [
            'total_basic_pay' => (float) $payrollRow->computed_total_basic_pay,
        ])->assertRedirect();

        $service->transition($period, PayrollPeriodStatus::ForReview, $admin);
        $period->refresh();
        $service->transition($period, PayrollPeriodStatus::Approved, $admin);
        $period->refresh();

        $final = $service->transition($period, PayrollPeriodStatus::Finalized, $admin);

        $this->assertSame(PayrollPeriodStatus::Finalized, $final->status);
    }
}
