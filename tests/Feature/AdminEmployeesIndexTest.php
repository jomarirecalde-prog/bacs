<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEmployeesIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_employees_index(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.employees.index'))
            ->assertOk();
    }

    public function test_supervisor_can_view_employees_index(): void
    {
        $supervisor = User::factory()->create([
            'role' => UserRole::Supervisor,
        ]);

        $this->actingAs($supervisor)
            ->get(route('admin.employees.index'))
            ->assertOk();
    }
}
