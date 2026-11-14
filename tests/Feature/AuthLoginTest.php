<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{user: User, employee: Employee} */
    private function activeEmployee(): array
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

        $user = User::factory()->create([
            'email' => 'login.me@bacs.test',
            'username' => 'login.user',
            'password' => 'ValidPass1!',
            'role' => UserRole::Employee,
            'status' => AccountStatus::Active,
            'must_change_password' => false,
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-LOGIN-01',
            'first_name' => 'Login',
            'last_name' => 'User',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
        ]);

        return ['user' => $user, 'employee' => $employee];
    }

    public function test_login_with_employee_number_succeeds_when_username_differs(): void
    {
        ['user' => $user, 'employee' => $employee] = $this->activeEmployee();

        $this->assertNotSame($employee->employee_number, $user->username);

        $response = $this->post(route('login'), [
            'login' => $employee->employee_number,
            'password' => 'ValidPass1!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_username_succeeds(): void
    {
        ['user' => $user] = $this->activeEmployee();

        $response = $this->post(route('login'), [
            'login' => $user->username,
            'password' => 'ValidPass1!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_username_is_case_insensitive(): void
    {
        ['user' => $user] = $this->activeEmployee();

        $response = $this->post(route('login'), [
            'login' => strtoupper($user->username),
            'password' => 'ValidPass1!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_email_succeeds(): void
    {
        ['user' => $user] = $this->activeEmployee();

        $response = $this->post(route('login'), [
            'login' => $user->email,
            'password' => 'ValidPass1!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_with_email_is_case_insensitive(): void
    {
        ['user' => $user] = $this->activeEmployee();

        $response = $this->post(route('login'), [
            'login' => strtoupper($user->email),
            'password' => 'ValidPass1!',
        ]);

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user);
    }
}
