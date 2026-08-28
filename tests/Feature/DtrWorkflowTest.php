<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DtrWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private WorkSchedule $schedule;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = WorkSchedule::query()->create([
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

        $this->department = Department::query()->create([
            'name' => 'IT Department',
            'status' => AccountStatus::Active,
        ]);
    }

    public function test_employee_can_record_am_time_in_once(): void
    {
        $employee = $this->makeEmployee('juan');
        $date = ManilaTime::todayDate();

        $this->post('/login', ['login' => 'juan', 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->actingAs($employee->user)
            ->postJson(route('attendance.time-in'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('attendance.am_time_in', '08:00 AM');

        $this->postJson(route('attendance.time-in'))
            ->assertStatus(422);

        $this->assertDatabaseCount('attendance', 1);
    }

    public function test_employee_cannot_record_outside_allowed_time_window(): void
    {
        $employee = $this->makeEmployee('maria');
        $date = ManilaTime::todayDate();

        $this->travelTo(ManilaTime::combineDateAndTime($date, '03:00'));
        $this->actingAs($employee->user)
            ->postJson(route('attendance.time-in'))
            ->assertStatus(422);
    }

    public function test_employee_can_record_full_sequence(): void
    {
        $employee = $this->makeEmployee('pedro');
        $date = ManilaTime::todayDate();

        $this->actingAs($employee->user);
        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->postJson(route('attendance.time-in'))->assertOk();
        $this->travelTo(ManilaTime::combineDateAndTime($date, '12:00'));
        $this->postJson(route('attendance.time-out'))->assertOk();
        $this->travelTo(ManilaTime::combineDateAndTime($date, '13:00'));
        $this->postJson(route('attendance.time-in'))->assertOk();
        $this->travelTo(ManilaTime::combineDateAndTime($date, '17:00'));
        $this->postJson(route('attendance.time-out'))->assertOk();

        $record = Attendance::query()->first();
        $this->assertNotNull($record->am_time_in);
        $this->assertNotNull($record->pm_time_out);
    }

    public function test_employee_cannot_view_another_employee_dtr(): void
    {
        $a = $this->makeEmployee('alpha');
        $b = $this->makeEmployee('beta');

        $this->actingAs($a->user)
            ->get(route('employee.dtr.show', $b))
            ->assertForbidden();

        $this->actingAs($a->user)
            ->get(route('admin.employees.show', $b))
            ->assertForbidden();
    }

    public function test_employee_cannot_access_admin_pages(): void
    {
        $employee = $this->makeEmployee('staff');

        $this->actingAs($employee->user)
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_admin_can_view_dashboard_and_edit_dtr_with_reason(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'admin']);
        $employee = $this->makeEmployee('worker');
        $date = ManilaTime::todayDate();

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->actingAs($employee->user)->postJson(route('attendance.time-in'))->assertOk();
        $record = Attendance::query()->first();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

        $this->actingAs($admin)
            ->put(route('admin.dtr.update', $record), [
                'am_time_in' => '08:03',
                'pm_time_out' => '17:02',
                'reason' => 'Employee forgot to clock out.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_edits', [
            'attendance_id' => $record->id,
            'reason' => 'Employee forgot to clock out.',
        ]);
    }

    public function test_duplicate_attendance_cannot_be_created_directly(): void
    {
        $employee = $this->makeEmployee('dup');
        $date = ManilaTime::todayDate();

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'am_time_in' => ManilaTime::now(),
            'status' => 'incomplete',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'am_time_in' => ManilaTime::now(),
            'status' => 'incomplete',
        ]);
    }

    public function test_employee_cannot_clock_in_for_another_employee(): void
    {
        $a = $this->makeEmployee('one');
        $b = $this->makeEmployee('two');
        $date = ManilaTime::todayDate();

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->actingAs($a->user)
            ->postJson(route('attendance.time-in'), ['employee_id' => $b->id])
            ->assertOk();

        $this->assertDatabaseHas('attendance', ['employee_id' => $a->id]);
        $this->assertDatabaseMissing('attendance', ['employee_id' => $b->id]);
    }

    public function test_employee_cannot_edit_dtr_through_admin_endpoint(): void
    {
        $employee = $this->makeEmployee('hacker');
        $date = ManilaTime::todayDate();
        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->actingAs($employee->user)->postJson(route('attendance.time-in'))->assertOk();
        $record = Attendance::query()->first();

        $this->actingAs($employee->user)
            ->put(route('admin.dtr.update', $record), [
                'am_time_in' => '08:00',
                'pm_time_out' => '17:00',
                'reason' => 'tamper',
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_access_protected_endpoints(): void
    {
        $this->postJson(route('attendance.time-in'))->assertUnauthorized();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_employee_can_change_password(): void
    {
        $employee = $this->makeEmployee('pwd');

        $this->actingAs($employee->user)
            ->put(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect();

        $this->assertTrue(password_verify('new-password', $employee->user->fresh()->password));
    }

    private function makeEmployee(string $username): Employee
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $username.'@bacs.test',
            'name' => ucfirst($username).' Test',
        ]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($username).'-001',
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $user->email,
            'department_id' => $this->department->id,
            'position' => 'Staff',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $this->schedule->id,
        ]);
    }
}
