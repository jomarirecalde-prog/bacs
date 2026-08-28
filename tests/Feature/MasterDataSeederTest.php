<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EmployeeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_exactly_forty_six_employees_and_six_departments(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(6, Department::query()->active()->count());
        $this->assertSame(46, Employee::query()->where('employee_number', 'like', 'BACS-2026-%')->active()->count());
        $this->assertSame(0, Employee::query()->whereNull('department_id')->count());
        $this->assertSame(46, Employee::query()->active()->pluck('employee_number')->unique()->count());
        $this->assertSame(46, Employee::query()->active()->whereHas('user')->count());
        $this->assertTrue(Employee::query()->where('full_name', 'Acompañado, Nancy G.')->exists());
        $this->assertTrue(Employee::query()->where('full_name', 'De La Cruz, Kenneth S.')->exists());
        $this->assertTrue(Employee::query()->where('employee_number', 'BACS-2026-0001')->exists());
        $this->assertTrue(Employee::query()->where('employee_number', 'BACS-2026-0052')->exists());
        $this->assertSame(5, Employee::query()->active()->whereHas('department', fn ($q) => $q->where('name', 'BOARD OF DIRECTORS AND CORPORATE OFFICERS'))->count());
        $this->assertSame(9, Employee::query()->active()->whereHas('department', fn ($q) => $q->where('name', 'PROJECT MANAGEMENT'))->count());
        $this->assertSame(2, Employee::query()->active()->whereHas('department', fn ($q) => $q->where('name', 'SALES & MARKETING'))->count());
        $this->assertSame(9, Employee::query()->active()->whereHas('department', fn ($q) => $q->where('name', 'ADMIN'))->count());
        $this->assertSame(4, Employee::query()->active()->whereHas('department', fn ($q) => $q->where('name', 'FINANCE'))->count());
        $this->assertSame(17, Employee::query()->active()->whereHas('department', fn ($q) => $q->where('name', 'OPERATION'))->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(46, Employee::query()->active()->where('employee_number', 'like', 'BACS-2026-%')->count());
        $this->assertSame(6, Department::query()->count());
        $this->assertSame(
            'Bacosa, Cesario Jr. A.',
            Employee::query()->where('employee_number', 'BACS-2026-0001')->value('full_name')
        );
        $this->assertSame(
            'Edon, Cody Mae',
            Employee::query()->where('employee_number', 'BACS-2026-0052')->value('full_name')
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

        $ceo = Employee::query()->where('employee_number', 'BACS-2026-0001')->first();
        $this->assertSame(UserRole::Supervisor, $ceo->user->role);
        $this->assertSame(UserRole::Admin, User::query()->where('username', 'admin')->first()->role);

        $field = Employee::query()->where('employee_number', 'BACS-2026-0014')->first();
        $this->assertSame(UserRole::Employee, $field->user->role);
        $this->assertSame('BACS-2026-0014', $field->employee_number);
        $this->assertTrue(Hash::check('password', $field->user->password));
        $this->assertFalse((bool) $field->user->must_change_password);
    }

    public function test_management_can_open_employee_profile_and_search_by_number(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->first();
        $employee = Employee::query()->where('employee_number', 'BACS-2026-0014')->first();

        $this->actingAs($admin)
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertSee('BACS-2026-0014')
            ->assertSee('Cayapas, Reymond I.')
            ->assertSee('OPERATION')
            ->assertSee('Project Team Leader')
            ->assertSee('View Complete DTR')
            ->assertSee('Days Present');

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['q' => 'BACS-2026-0014']))
            ->assertOk()
            ->assertSee('Cayapas, Reymond I.')
            ->assertDontSee('Acompañado, Nancy G.');
    }

    public function test_employee_can_update_permitted_profile_information(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = Employee::query()->where('employee_number', 'BACS-2026-0014')->first();

        $this->actingAs($employee->user)
            ->putJson(route('profile.update'), [
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'last_name' => $employee->last_name,
                'suffix' => $employee->suffix,
                'email' => $employee->email,
                'contact_number' => '09171234567',
            ])
            ->assertOk();

        $this->assertSame('09171234567', $employee->fresh()->contact_number);
    }

    public function test_admin_can_search_and_filter_employees(): void
    {
        $this->seed(DatabaseSeeder::class);
        $admin = User::query()->where('username', 'admin')->first();

        $this->actingAs($admin)
            ->get(route('admin.employees.index', ['q' => 'Cayapas']))
            ->assertOk()
            ->assertSee('Cayapas, Reymond I.')
            ->assertSee('Project Team Leader')
            ->assertSee('OPERATION')
            ->assertDontSee('Acompañado, Nancy G.');

        $operation = Department::query()->where('name', 'OPERATION')->first();

        $this->actingAs($admin)
            ->get(route('admin.dashboard', ['department_id' => $operation->id]))
            ->assertOk()
            ->assertSee('Cayapas, Reymond I.')
            ->assertDontSee('Gaid, Dorabel Y.');
    }

    public function test_employee_cannot_view_another_employees_dtr_from_master_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $one = Employee::query()->where('employee_number', 'BACS-2026-0014')->first();
        $two = Employee::query()->where('employee_number', 'BACS-2026-0030')->first();

        $this->actingAs($one->user)
            ->get(route('employee.dtr.show', $two))
            ->assertForbidden();

        $this->actingAs($one->user)
            ->get(route('admin.employees.show', $two))
            ->assertForbidden();
    }

    public function test_employee_can_access_dashboard_with_default_password(): void
    {
        $this->seed(DatabaseSeeder::class);
        $employee = Employee::query()->where('employee_number', 'BACS-2026-0014')->first();

        $this->assertFalse((bool) $employee->user->must_change_password);
        $this->assertTrue(Hash::check('password', $employee->user->password));
        $this->assertSame(UserRole::Employee, $employee->user->role);

        $this->actingAs($employee->user)
            ->get(route('employee.dashboard'))
            ->assertOk();
    }

    public function test_name_parser_keeps_suffixes_and_middle_initials(): void
    {
        $seeder = new EmployeeSeeder;

        $this->assertSame(
            ['first_name' => 'Cesario Jr.', 'middle_name' => 'A.', 'last_name' => 'Bacosa'],
            $seeder->parseName('Bacosa, Cesario Jr. A.')
        );
        $this->assertSame(
            ['first_name' => 'Mark Jayson', 'middle_name' => 'H.', 'last_name' => 'Germina'],
            $seeder->parseName('Germina, Mark Jayson H.')
        );
        $this->assertSame(
            ['first_name' => 'Nancy', 'middle_name' => 'G.', 'last_name' => 'Acompañado'],
            $seeder->parseName('Acompañado, Nancy G.')
        );
        $this->assertSame(
            ['first_name' => 'Kenneth', 'middle_name' => 'S.', 'last_name' => 'De La Cruz'],
            $seeder->parseName('De La Cruz, Kenneth S.')
        );
        $this->assertSame(
            ['first_name' => 'Matthew John Clifford', 'middle_name' => 'D.', 'last_name' => 'Paredes'],
            $seeder->parseName('Paredes, Matthew John Clifford D.')
        );
    }
}
