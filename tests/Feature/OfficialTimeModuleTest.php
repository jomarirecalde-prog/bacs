<?php

namespace Tests\Feature;

use App\Enums\ApprovalTransactionType;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveDecision;
use App\Enums\OfficialTimeStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OfficialTimeRequest;
use App\Models\OfficialTimeType;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Enums\AccountStatus;
use App\Services\OfficialTimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class OfficialTimeModuleTest extends TestCase
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

        Department::query()->create(['name' => 'Operations', 'status' => AccountStatus::Active]);

        $ceo = User::factory()->create(['role' => UserRole::Supervisor, 'username' => 'ceo-ot']);
        Employee::query()->create([
            'user_id' => $ceo->id,
            'employee_number' => 'CEO-OT',
            'first_name' => 'CEO',
            'last_name' => 'Official',
            'email' => $ceo->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
        Setting::query()->updateOrCreate(['key' => 'ceo_user_id'], ['value' => (string) $ceo->id]);
        $this->seedDefaultCentralApprovalsFromCeo((int) $ceo->fresh('employee')->employee->id);
        $this->resetCentralApproval(ApprovalTransactionType::OfficialTime, [], (int) $ceo->fresh('employee')->employee->id);

        OfficialTimeType::query()->create([
            'code' => 'meeting',
            'name' => 'Official Meeting',
            'is_active' => true,
        ]);
    }

    public function test_employee_can_submit_and_ceo_can_approve(): void
    {
        [$employee, $ceoEmployee] = $this->twoEmployees();
        $service = app(OfficialTimeService::class);

        $request = $service->submit($employee, $employee->user, $this->payload());
        $service->afterSubmit($request);

        $this->assertSame(OfficialTimeStatus::PendingCeoFinalApproval, $request->status);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $ceoEmployee->user_id,
        ]);

        $request = $service->decide($request->fresh(), $ceoEmployee->user, LeaveDecision::Approved);
        $this->assertSame(OfficialTimeStatus::Approved, $request->status);
    }

    public function test_overlap_is_blocked(): void
    {
        $employee = $this->makeEmployee('OT-1');
        $service = app(OfficialTimeService::class);
        $service->submit($employee, $employee->user, $this->payload());

        $this->expectException(ValidationException::class);
        $service->submit($employee, $employee->user, $this->payload([
            'am_time_in' => '11:00',
            'am_time_out' => '11:30',
        ]));
    }

    public function test_unauthorized_employee_cannot_view_other_request(): void
    {
        $a = $this->makeEmployee('OT-A');
        $b = $this->makeEmployee('OT-B');
        $service = app(OfficialTimeService::class);
        $request = $service->saveDraft($a, $a->user, $this->payload());

        $this->actingAs($b->user)
            ->get(route('employee.official-time.show', $request))
            ->assertForbidden();
    }

    public function test_reject_requires_reason(): void
    {
        [$employee, $ceoEmployee] = $this->twoEmployees();
        $service = app(OfficialTimeService::class);
        $request = $service->submit($employee, $employee->user, $this->payload());

        $this->expectException(ValidationException::class);
        $service->decide($request, $ceoEmployee->user, LeaveDecision::Denied, '');
    }

    /** @return array{0: Employee, 1: Employee} */
    private function twoEmployees(): array
    {
        $requester = $this->makeEmployee('OT-REQ');
        $ceo = Employee::query()->where('employee_number', 'CEO-OT')->firstOrFail();

        return [$requester, $ceo];
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

    /** @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'official_time_type_id' => OfficialTimeType::query()->value('id'),
            'date' => now()->addDay()->toDateString(),
            'am_time_in' => '10:00',
            'am_time_out' => '12:00',
            'pm_time_in' => '13:00',
            'pm_time_out' => '17:00',
        ], $overrides);
    }
}
