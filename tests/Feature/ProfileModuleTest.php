<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileModuleTest extends TestCase
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
            'status' => 'active',
        ]);

        $this->department = Department::query()->create([
            'name' => 'Operations',
            'status' => 'active',
        ]);
    }

    public function test_employee_can_view_profile_page(): void
    {
        $employee = $this->makeEmployee('profileview');

        $this->actingAs($employee->user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee($employee->employee_number)
            ->assertSee('Personal Information')
            ->assertSee('Change Password');
    }

    public function test_employee_can_update_permitted_fields(): void
    {
        $employee = $this->makeEmployee('profileedit');

        $this->actingAs($employee->user)
            ->putJson(route('profile.update'), [
                'first_name' => 'Updated',
                'middle_name' => 'M',
                'last_name' => 'Employee',
                'suffix' => 'Jr.',
                'email' => 'updated@bacs.test',
                'contact_number' => '09171234567',
                'address' => '123 Main St, Puerto Princesa',
                'birth_date' => '1990-05-15',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $employee->refresh();
        $this->assertSame('Updated', $employee->first_name);
        $this->assertSame('09171234567', $employee->contact_number);
        $this->assertSame('updated@bacs.test', $employee->email);
        $this->assertSame('updated@bacs.test', $employee->user->fresh()->email);
    }

    public function test_employee_cannot_update_restricted_fields_via_mass_assignment(): void
    {
        $employee = $this->makeEmployee('restricted');

        $this->actingAs($employee->user)
            ->putJson(route('profile.update'), [
                'first_name' => 'Safe',
                'middle_name' => null,
                'last_name' => 'User',
                'suffix' => null,
                'email' => 'safe@bacs.test',
                'contact_number' => '09170000000',
                'employee_number' => 'HACKED-001',
                'department_id' => 999,
                'position' => 'CEO',
            ])
            ->assertOk();

        $employee->refresh();
        $this->assertSame('RESTRICTED-001', $employee->employee_number);
        $this->assertSame('Staff', $employee->position);
        $this->assertSame($this->department->id, $employee->department_id);
    }

    public function test_employee_can_upload_and_remove_profile_photo(): void
    {
        Storage::fake('public');
        $employee = $this->makeEmployee('photo');

        $file = UploadedFile::fake()->image('avatar.jpg', 400, 400);

        $this->actingAs($employee->user)
            ->postJson(route('profile.photo.update'), ['photo' => $file])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $employee->refresh();
        $this->assertNotNull($employee->photo);
        $storedPath = $employee->photo;
        Storage::disk('public')->assertExists($storedPath);

        $this->actingAs($employee->user)
            ->deleteJson(route('profile.photo.remove'))
            ->assertOk();

        $this->assertNull($employee->fresh()->photo);
        Storage::disk('public')->assertMissing($storedPath);
    }

    public function test_employee_can_change_password(): void
    {
        $employee = $this->makeEmployee('pwdprofile');

        $this->actingAs($employee->user)
            ->putJson(route('profile.password.update'), [
                'current_password' => 'password',
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertTrue(password_verify('new-secure-password', $employee->user->fresh()->password));
        $this->assertNotNull($employee->user->fresh()->password_changed_at);
    }

    public function test_profile_me_returns_only_authenticated_user(): void
    {
        $one = $this->makeEmployee('userone');
        $two = $this->makeEmployee('usertwo');

        $this->actingAs($one->user)
            ->getJson(route('profile.me'))
            ->assertOk()
            ->assertJsonPath('employee.employee_number', $one->employee_number)
            ->assertJsonMissing(['employee_number' => $two->employee_number]);
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
