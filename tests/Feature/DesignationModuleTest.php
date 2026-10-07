<?php

namespace Tests\Feature;

use App\Enums\SalaryType;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationModuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_designation_with_manual_compensation(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->post(route('admin.designations.store'), [
            'designation_name' => 'Technical Staff',
            'designation_code' => 'TECH_STAFF',
            'default_pay_type' => SalaryType::Daily->value,
            'default_daily_rate' => 700,
            'default_working_hours_per_day' => 8,
            'default_working_days_per_period' => 11,
            'is_active' => 1,
        ]);

        $designation = Designation::query()->where('designation_code', 'TECH_STAFF')->first();
        $this->assertNotNull($designation);
        $response->assertRedirect(route('admin.designations.show', $designation));

        $this->assertSame('700.00', (string) $designation->default_daily_rate);
        $this->assertSame(SalaryType::Daily, $designation->default_pay_type);
    }
}
