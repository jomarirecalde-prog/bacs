<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_exactly_forty_four_employees_and_six_departments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, Department::query()->active()->count());
        $this->assertSame(44, Employee::query()->where('employee_number', 'like', 'BACS-2026-%')->count());
        $this->assertSame(44, Employee::query()->active()->where('employee_number', 'like', 'BACS-2026-%')->count());
        $this->assertSame(0, Employee::query()->whereNull('department_id')->count());
        $this->assertSame(44, Employee::query()->pluck('employee_number')->unique()->count());
        $this->assertSame(44, Employee::query()->whereHas('user')->count());
        $this->assertTrue(Employee::query()->where('full_name', 'Acompañado, Nancy')->exists());
        $this->assertTrue(Employee::query()->where('full_name', 'Dela Cruz, Kenneth')->exists());
        $this->assertTrue(Employee::query()->where('employee_number', 'BACS-2026-0001')->exists());
        $this->assertTrue(Employee::query()->where('employee_number', 'BACS-2026-0044')->exists());
        $this->assertSame(5, Employee::query()->whereHas('department', fn ($q) => $q->where('name', 'BOARD OF DIRECTORS AND CORPORATE OFFICERS'))->count());
        $this->assertSame(8, Employee::query()->whereHas('department', fn ($q) => $q->where('name', 'PROJECT MANAGEMENT'))->count());
        $this->assertSame(2, Employee::query()->whereHas('department', fn ($q) => $q->where('name', 'SALES & MARKETING'))->count());
        $this->assertSame(9, Employee::query()->whereHas('department', fn ($q) => $q->where('name', 'ADMIN'))->count());
        $this->assertSame(4, Employee::query()->whereHas('department', fn ($q) => $q->where('name', 'FINANCE'))->count());
        $this->assertSame(16, Employee::query()->whereHas('department', fn ($q) => $q->where('name', 'OPERATION'))->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(44, Employee::query()->where('employee_number', 'like', 'BACS-2026-%')->count());
        $this->assertSame(6, Department::query()->count());
        $this->assertSame(
            'Bacosa, Cesario Jr',
            Employee::query()->where('employee_number', 'BACS-2026-0001')->value('full_name')
        );
        $this->assertSame(
            'Balbin, Sajied',
            Employee::query()->where('employee_number', 'BACS-2026-0044')->value('full_name')
        );
    }

    public function test_dashboard_counts_active_employees_from_the_database(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->first();
        $active = Employee::query()->active()->count();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['total_employees'] === $active)
            ->assertSee('Department Attendance Summary')
            ->assertSee('PROJECT MANAGEMENT')
            ->assertSee('SALES & MARKETING')
            ->assertSee('OPERATION');
    }

    public function test_corporate_officers_are_management_and_staff_are_employees(): void
    {
        $this->seed(DatabaseSeeder::class);

        $ceo = Employee::query()->where('full_name', 'Bacosa, Cesario Jr')->first();
        $this->assertSame(UserRole::Supervisor, $ceo->user->role);
        $this->assertSame(UserRole::Admin, User::query()->where('username', 'admin')->first()->role);

        $field = Employee::query()->where('full_name', 'Cayapas, Reymond')->first();
        $this->assertSame(UserRole::Employee, $field->user->role);
        $this->assertSame('BACS-2026-0029', $field->employee_number);
        $this->assertNotEquals('password', $field->user->getRawOriginal('password'));
    }

    public function test_management_can_open_employee_profile_and_search_by_number(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->first();
        $employee = Employee::query()->where('full_name', 'Cayapas, Reymond')->first();

        $this->actingAs($admin)
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertSee('BACS-2026-0029')
            ->assertSee('Cayapas, Reymond')
            ->assertSee('OPERATION')
            ->assertSee('Field Engineer')
            ->assertSee('View Complete DTR')
            ->assertSee('Days Present');

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['q' => 'BACS-2026-0029']))
            ->assertOk()
            ->assertSee('Cayapas, Reymond')
            ->assertDontSee('Acompañado, Nancy');
    }

    public function test_employee_can_update_permitted_profile_information(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = Employee::query()->where('employee_number', 'BACS-2026-0029')->first();
        $employee->user->update(['must_change_password' => false, 'password' => 'password']);

        $this->actingAs($employee->user)
            ->put(route('profile.update'), ['contact_number' => '09171234567'])
            ->assertRedirect();

        $this->assertSame('09171234567', $employee->fresh()->contact_number);
    }

    public function test_admin_can_search_and_filter_employees(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->first();

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['q' => 'Cayapas']))
            ->assertOk()
            ->assertSee('Cayapas, Reymond')
            ->assertSee('Field Engineer')
            ->assertSee('OPERATION')
            ->assertDontSee('Acompañado, Nancy');

        $operation = Department::query()->where('name', 'OPERATION')->first();

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['department_id' => $operation->id]))
            ->assertOk()
            ->assertSee('Cayapas, Reymond')
            ->assertDontSee('Gaid, Dorabel Y.');
    }

    public function test_employee_cannot_view_another_employees_dtr_from_master_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $one = Employee::query()->where('employee_number', 'BACS-2026-0029')->first();
        $two = Employee::query()->where('employee_number', 'BACS-2026-0030')->first();
        $one->user->update(['must_change_password' => false, 'password' => 'password']);

        $this->actingAs($one->user)
            ->get(route('employee.dtr.show', $two))
            ->assertForbidden();

        $this->actingAs($one->user)
            ->get(route('admin.employees.show', $two))
            ->assertForbidden();
    }

    public function test_temporary_password_must_be_changed_before_using_the_system(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = Employee::query()->where('employee_number', 'BACS-2026-0029')->first();

        $this->assertTrue((bool) $employee->user->must_change_password);
        $this->assertSame(UserRole::Employee, $employee->user->role);

        $this->actingAs($employee->user)
            ->get(route('employee.dashboard'))
            ->assertRedirect(route('profile.password'));
    }

    public function test_name_parser_keeps_suffixes_and_middle_initials(): void
    {
        $seeder = new EmployeeSeeder;

        $this->assertSame(
            ['first_name' => 'Cesario Jr', 'middle_name' => null, 'last_name' => 'Bacosa'],
            $seeder->parseName('Bacosa, Cesario Jr')
        );
        $this->assertSame(
            ['first_name' => 'Mark Jayson', 'middle_name' => 'H', 'last_name' => 'Germina'],
            $seeder->parseName('Germina, Mark Jayson H')
        );
        $this->assertSame(
            ['first_name' => 'Nancy', 'middle_name' => null, 'last_name' => 'Acompañado'],
            $seeder->parseName('Acompañado, Nancy')
        );
        $this->assertSame(
            ['first_name' => 'Kenneth', 'middle_name' => null, 'last_name' => 'Dela Cruz'],
            $seeder->parseName('Dela Cruz, Kenneth')
        );
    }
}
