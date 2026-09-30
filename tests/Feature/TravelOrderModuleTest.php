<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\TravelOrder;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\TravelOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TravelOrderModuleTest extends TestCase
{
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

        $ceo = User::factory()->create(['role' => UserRole::Supervisor, 'username' => 'ceo-travel']);
        Employee::query()->create([
            'user_id' => $ceo->id,
            'employee_number' => 'CEO-T',
            'first_name' => 'CEO',
            'last_name' => 'Travel',
            'email' => $ceo->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
        Setting::query()->updateOrCreate(['key' => 'ceo_user_id'], ['value' => (string) $ceo->id]);
    }

    public function test_requester_is_not_auto_included_as_traveler(): void
    {
        [$requester, $traveler] = $this->twoEmployees();

        $service = app(TravelOrderService::class);
        $order = $service->submit($requester, $requester->user, $this->payload([$traveler->id]));
        $service->afterSubmit($order);

        $order->load('personnel');
        $this->assertSame($requester->id, $order->requester_id);
        $this->assertCount(1, $order->personnel);
        $this->assertSame($traveler->id, $order->personnel->first()->employee_id);
        $this->assertFalse($order->personnel->contains('employee_id', $requester->id));
    }

    public function test_include_requester_checkbox_adds_traveler_once(): void
    {
        [$requester, $traveler] = $this->twoEmployees();
        $data = $this->payload([$traveler->id]);
        $data['include_requester_as_traveler'] = true;

        $service = app(TravelOrderService::class);
        $order = $service->submit($requester, $requester->user, $data);

        $ids = $order->personnel()->pluck('employee_id')->sort()->values()->all();
        $this->assertSame([$requester->id, $traveler->id], $ids);
    }

    public function test_unauthorized_user_cannot_endorse(): void
    {
        [$requester, $traveler, $intruder] = $this->threeEmployees();
        $service = app(TravelOrderService::class);
        $order = $service->submit($requester, $requester->user, $this->payload([$traveler->id]));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->decide($order, $intruder->user, \App\Enums\LeaveDecision::Approved);
    }

    /** @return array{0: Employee, 1: Employee} */
    private function twoEmployees(): array
    {
        $a = $this->makeEmployee('REQ-1');
        $b = $this->makeEmployee('TRV-1');

        return [$a, $b];
    }

    /** @return array{0: Employee, 1: Employee, 2: Employee} */
    private function threeEmployees(): array
    {
        [$a, $b] = $this->twoEmployees();
        $c = $this->makeEmployee('INT-1');

        return [$a, $b, $c];
    }

    private function makeEmployee(string $number): Employee
    {
        $user = User::factory()->create(['role' => UserRole::Employee]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => $number,
            'first_name' => $number,
            'last_name' => 'Test',
            'email' => $user->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
        $employee->setRelation('user', $user);

        return $employee;
    }

    /** @param  list<int>  $travelerIds
     * @return array<string, mixed>
     */
    private function payload(array $travelerIds): array
    {
        return [
            'official_station' => 'Main Office',
            'destinations' => ['Cebu City'],
            'date_start' => now()->addWeek()->toDateString(),
            'date_end' => now()->addWeek()->addDay()->toDateString(),
            'purpose' => 'Site inspection',
            'transportation' => 'land',
            'traveler_ids' => $travelerIds,
        ];
    }
}
