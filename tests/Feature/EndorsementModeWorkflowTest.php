<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApprovalTransactionType;
use App\Enums\EmploymentStatus;
use App\Enums\EndorsementMode;
use App\Enums\LeaveDecision;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\LeaveApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class EndorsementModeWorkflowTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    public function test_parallel_endorsement_requires_all_endorsers(): void
    {
        [$staff, $endorserA, $endorserB, $final] = $this->people();

        $this->resetCentralApproval(
            ApprovalTransactionType::LeaveApplication,
            [$endorserA->id, $endorserB->id],
            $final->id,
            endorsementMode: EndorsementMode::Parallel,
        );

        app(LeaveApplicationService::class)->submit($staff, $staff->user, $this->payload());

        $application = LeaveApplication::query()->firstOrFail();
        $service = app(LeaveApplicationService::class);

        $service->decide($application, $endorserA->user, LeaveDecision::Approved, 'OK');
        $application->refresh();
        $this->assertTrue($application->status->isOpen());

        $service->decide($application, $endorserB->user, LeaveDecision::Approved, 'OK');
        $application->refresh();
        $this->assertSame(\App\Enums\LeaveStatus::PendingCeoFinalApproval, $application->status);
    }

    public function test_sequential_endorsement_activates_next_endorser(): void
    {
        [$staff, $endorserA, $endorserB, $final] = $this->people();

        $this->resetCentralApproval(
            ApprovalTransactionType::LeaveApplication,
            [$endorserA->id, $endorserB->id],
            $final->id,
            endorsementMode: EndorsementMode::Sequential,
        );

        app(LeaveApplicationService::class)->submit($staff, $staff->user, $this->payload());

        $application = LeaveApplication::query()->firstOrFail();
        $this->assertTrue(
            $application->assignments()->where('user_id', $endorserB->user_id)->where('status', 'waiting')->exists()
        );

        $service = app(LeaveApplicationService::class);

        try {
            $service->decide($application, $endorserB->user, LeaveDecision::Approved);
            $this->fail('Second endorser should not act before the first in sequential mode.');
        } catch (\Illuminate\Validation\ValidationException) {
            // expected
        }

        $service->decide($application->fresh(), $endorserA->user, LeaveDecision::Approved, 'First');
        $application->refresh();
        $this->assertTrue(
            $application->assignments()->where('user_id', $endorserB->user_id)->where('status', 'pending')->exists()
        );
    }

    /** @return array{0: Employee, 1: Employee, 2: Employee, 3: Employee} */
    private function people(): array
    {
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

        Department::query()->create(['name' => 'Ops', 'status' => AccountStatus::Active]);

        $staff = $this->emp('staff', UserRole::Employee);
        $a = $this->emp('end-a', UserRole::Supervisor);
        $b = $this->emp('end-b', UserRole::Supervisor);
        $final = $this->emp('final', UserRole::Supervisor);

        return [$staff, $a, $b, $final];
    }

    private function emp(string $key, UserRole $role): Employee
    {
        $user = User::factory()->create(['role' => $role]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($key),
            'first_name' => $key,
            'last_name' => 'User',
            'email' => $user->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'leave_type' => 'vacation',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
            'reason' => 'Endorsement mode test',
            'declaration_accepted' => '1',
            'employee_signature' => 'sig',
        ];
    }
}
