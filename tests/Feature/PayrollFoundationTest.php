<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\PayrollPeriodStatus;
use App\Enums\SalaryType;
use App\Enums\UserRole;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\EmployeeSalaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_payroll_period_and_compute_attendance(): void
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
        $user = User::factory()->create(['role' => UserRole::Employee]);
        $designation = Designation::query()->create([
            'designation_code' => 'TECH_STAFF',
            'designation_name' => 'Technical Staff',
        ]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-9999',
            'first_name' => 'Pay',
            'last_name' => 'Roll',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        app(EmployeeSalaryService::class)->assign($employee, [
            'salary_type' => SalaryType::Monthly->value,
            'amount' => 10000,
            'effective_from' => '2026-09-01',
        ], $admin);

        $this->actingAs($admin)
            ->post(route('admin.payroll.periods.store'), [
                'period_name' => 'Payroll Sep 11–25, 2026',
                'start_date' => '2026-09-11',
                'end_date' => '2026-09-25',
            ])
            ->assertRedirect();

        $period = PayrollPeriod::query()->first();
        $this->assertNotNull($period);
        $this->assertSame(PayrollPeriodStatus::Draft, $period->status);

        $this->actingAs($admin)
            ->post(route('admin.payroll.periods.compute', $period))
            ->assertRedirect();

        $period->refresh();
        $this->assertSame(PayrollPeriodStatus::Computed, $period->status);
        $this->assertDatabaseHas('payroll_attendance_summary', [
            'payroll_period_id' => $period->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_employee_cannot_access_payroll_dashboard(): void
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

        $user = User::factory()->create(['role' => UserRole::Employee]);
        Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-9998',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
        ]);

        $this->actingAs($user)
            ->get(route('admin.payroll.dashboard'))
            ->assertForbidden();
    }
}
