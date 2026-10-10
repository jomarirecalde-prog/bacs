<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\SalaryType;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryAssignTest extends TestCase
{
    use RefreshDatabase;

    public function test_second_salary_assignment_supersedes_prior_active_record(): void
    {
        $schedule = $this->defaultSchedule();
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $designation = Designation::query()->create([
            'designation_code' => 'TEST',
            'designation_name' => 'Test',
        ]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'BACS-2026-0001',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
            'designation_id' => $designation->id,
        ]);

        $payload = [
            'salary_type' => SalaryType::SemiMonthly->value,
            'basic_salary' => 10000,
            'gross_compensation' => 10200,
            'effective_from' => '2026-09-01',
        ];

        $this->actingAs($admin)->post(route('admin.payroll.employees.salary.store', $employee), $payload)
            ->assertRedirect();

        $payload['basic_salary'] = 12000;
        $payload['gross_compensation'] = 12200;
        $payload['effective_from'] = '2026-10-01';

        $this->actingAs($admin)->post(route('admin.payroll.employees.salary.store', $employee), $payload)
            ->assertRedirect();

        $this->assertDatabaseCount('employee_salary_history', 2);
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
