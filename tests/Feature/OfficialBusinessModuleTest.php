<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OfficialBusinessStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OfficialBusinessRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\OfficialBusinessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class OfficialBusinessModuleTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        WorkSchedule::query()->create([
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

        Department::query()->create(['name' => 'Field', 'status' => AccountStatus::Active]);

        $ceo = User::factory()->create(['role' => UserRole::Supervisor, 'username' => 'ceo-ob']);
        Employee::query()->create([
            'user_id' => $ceo->id,
            'employee_number' => 'CEO-OB',
            'first_name' => 'CEO',
            'last_name' => 'OB',
            'email' => $ceo->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
        $this->seedDefaultCentralApprovalsFromCeo((int) $ceo->fresh('employee')->employee->id);
        $this->resetCentralApproval(\App\Enums\ApprovalTransactionType::TravelOrder, [], (int) $ceo->fresh('employee')->employee->id);
    }

    public function test_employee_can_save_draft_and_submit_official_business(): void
    {
        $employee = $this->employeeUser();

        $service = app(OfficialBusinessService::class);
        $draft = $service->saveDraft($employee, $employee->user, $this->payload());
        $this->assertSame(OfficialBusinessStatus::Draft, $draft->status);
        $this->assertStringStartsWith('OB-', $draft->reference_number);

        $submitted = $service->submit($employee, $employee->user, $this->payload(), $draft);
        $service->afterSubmit($submitted);

        $this->assertNotSame(OfficialBusinessStatus::Draft, $submitted->fresh()->status);
        $this->assertDatabaseHas('official_business_participants', [
            'official_business_request_id' => $submitted->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_travel_orders_page_links_to_official_business(): void
    {
        $employee = $this->employeeUser();

        $this->actingAs($employee->user)
            ->get(route('employee.travel-orders.index', ['tab' => 'mine']))
            ->assertOk()
            ->assertSee('Official Business')
            ->assertSee('New OB Request');
    }

  private function employeeUser(): Employee
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);
        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'OB-EMP-1',
            'first_name' => 'Test',
            'last_name' => 'Employee',
            'email' => $user->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'official_station' => 'Palawan',
            'purpose' => 'Site inspection',
            'purpose_particular' => 'Quarterly audit',
            'destination_from' => 'Puerto Princesa',
            'destination_to' => 'El Nido',
            'date_covered_start' => now()->addDays(3)->toDateString(),
            'date_covered_end' => now()->addDays(3)->toDateString(),
            'departure_time' => '07:00',
            'transportation_mode' => 'company_vehicle',
            'vehicle_name' => 'Pickup',
            'plate_number' => 'ABC-123',
            'accommodation_type' => 'Hotel',
            'accommodation_particular' => 'Standard room',
            'participant_employee_ids' => [],
            'include_requester_as_participant' => true,
            'external_participants' => [],
        ];
    }
}
