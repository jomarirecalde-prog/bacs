<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\ManualSalaryComputationMode;
use App\Enums\ManualSalaryComputationStatus;
use App\Enums\ManualSalaryPayableIntent;
use App\Enums\SalaryType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\ManualSalaryComputation;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualSalaryComputationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_period_based_manual_computation(): void
    {
        [$admin, $employee, $period] = $this->fixtures();

        $response = $this->actingAs($admin)->post(route('admin.payroll.employees.manual-computations.store', $employee), [
            'computation_mode' => ManualSalaryComputationMode::Period->value,
            'payable_intent' => ManualSalaryPayableIntent::Standard->value,
            'payroll_period_id' => $period->id,
            'coverage_start' => $period->start_date->toDateString(),
            'coverage_end' => $period->end_date->toDateString(),
        ]);

        $computation = ManualSalaryComputation::query()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($computation);
        $response->assertRedirect(route('admin.payroll.employees.manual-computations.show', [$employee, $computation]));

        $this->assertSame(ManualSalaryComputationStatus::Draft, $computation->status);
        $this->assertGreaterThan(0, (float) $computation->basic_pay);
        $this->assertDatabaseHas('manual_salary_computation_lines', [
            'computation_id' => $computation->id,
            'code' => 'basic_pay',
        ]);
    }

    public function test_period_mode_accepts_payroll_period_without_manual_dates(): void
    {
        [$admin, $employee, $period] = $this->fixtures();

        $this->actingAs($admin)->post(route('admin.payroll.employees.manual-computations.store', $employee), [
            'computation_mode' => ManualSalaryComputationMode::Period->value,
            'payable_intent' => ManualSalaryPayableIntent::Standard->value,
            'payroll_period_id' => $period->id,
            'coverage_start' => '',
            'coverage_end' => '',
        ])->assertRedirect();

        $computation = ManualSalaryComputation::query()->where('employee_id', $employee->id)->first();
        $this->assertSame($period->start_date->toDateString(), $computation->coverage_start->toDateString());
    }

    public function test_duplicate_standard_computation_is_blocked(): void
    {
        [$admin, $employee, $period] = $this->fixtures();

        ManualSalaryComputation::query()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'computation_mode' => ManualSalaryComputationMode::Period,
            'payable_intent' => ManualSalaryPayableIntent::Standard,
            'coverage_start' => $period->start_date,
            'coverage_end' => $period->end_date,
            'status' => ManualSalaryComputationStatus::Finalized,
            'prepared_by' => $admin->id,
            'prepared_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.payroll.employees.manual-computations.store', $employee), [
            'computation_mode' => ManualSalaryComputationMode::Period->value,
            'payable_intent' => ManualSalaryPayableIntent::Standard->value,
            'payroll_period_id' => $period->id,
            'coverage_start' => $period->start_date->toDateString(),
            'coverage_end' => $period->end_date->toDateString(),
        ])->assertSessionHasErrors('coverage_start');
    }

    public function test_manual_override_requires_reason(): void
    {
        [$admin, $employee, $period] = $this->fixtures();

        $computation = ManualSalaryComputation::query()->create([
            'employee_id' => $employee->id,
            'payroll_period_id' => $period->id,
            'computation_mode' => ManualSalaryComputationMode::Period,
            'payable_intent' => ManualSalaryPayableIntent::Standard,
            'coverage_start' => $period->start_date,
            'coverage_end' => $period->end_date,
            'status' => ManualSalaryComputationStatus::Draft,
            'prepared_by' => $admin->id,
            'prepared_at' => now(),
        ]);

        $this->actingAs($admin)->post(route('admin.payroll.employees.manual-computations.recalculate', [$employee, $computation]), [
            'overrides' => [
                ['field_key' => 'total_basic_pay', 'manual_value' => 5000, 'reason' => ''],
            ],
        ])->assertSessionHasErrors();
    }

    /**
     * @return array{0: User, 1: Employee, 2: PayrollPeriod}
     */
    private function fixtures(): array
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
            'designation_code' => 'ENGR',
            'designation_name' => 'Engineer',
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-MAN-001',
            'first_name' => 'Manual',
            'last_name' => 'Tester',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        $this->actingAs($admin)->post(route('admin.payroll.employees.salary.store', $employee), [
            'salary_type' => SalaryType::SemiMonthly->value,
            'basic_salary' => 12000,
            'gross_compensation' => 12000,
            'effective_from' => '2026-09-01',
        ])->assertRedirect();

        $period = PayrollPeriod::query()->create([
            'period_name' => 'Manual Test Period',
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-25',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);

        return [$admin, $employee, $period];
    }
}
