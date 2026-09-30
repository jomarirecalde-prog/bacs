<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApprovalTransactionType;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\CentralApprovalDutyService;
use App\Services\LeaveApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class CentralApprovalWorkflowTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    public function test_configured_endorser_gets_sidebar_module_with_pending_count(): void
    {
        [$staff, $endorser, $final] = $this->employees();

        $this->resetCentralApproval(
            ApprovalTransactionType::LeaveApplication,
            [$endorser->id],
            $final->id,
        );

        $service = app(LeaveApplicationService::class);
        $service->submit($staff, $staff->user, [
            'leave_type' => 'vacation',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
            'reason' => 'Test',
            'declaration_accepted' => '1',
            'employee_signature' => 'sig',
        ]);

        $modules = app(CentralApprovalDutyService::class)->sidebarModules($endorser->user);
        $labels = collect($modules)->pluck('label')->all();

        $this->assertContains('Leave Application Endorsement', $labels);
        $endorsement = collect($modules)->firstWhere('label', 'Leave Application Endorsement');
        $this->assertSame(1, $endorsement['count']);
    }

    public function test_unassigned_user_cannot_decide_leave_via_service(): void
    {
        [$staff, $endorser, $final] = $this->employees();
        $intruder = User::factory()->create(['role' => UserRole::Employee]);
        Employee::query()->create([
            'user_id' => $intruder->id,
            'employee_number' => 'INT-001',
            'first_name' => 'Int',
            'last_name' => 'User',
            'email' => $intruder->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);

        $this->resetCentralApproval(
            ApprovalTransactionType::LeaveApplication,
            [$endorser->id],
            $final->id,
        );

        $service = app(LeaveApplicationService::class);
        $service->submit($staff, $staff->user, [
            'leave_type' => 'vacation',
            'start_date' => '2026-10-02',
            'end_date' => '2026-10-02',
            'reason' => 'Test',
            'declaration_accepted' => '1',
            'employee_signature' => 'sig',
        ]);

        $application = LeaveApplication::query()->firstOrFail();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->decide($application, $intruder, \App\Enums\LeaveDecision::Approved);
    }

    /** @return array{0: Employee, 1: Employee, 2: Employee} */
    private function employees(): array
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

        $staffUser = User::factory()->create(['role' => UserRole::Employee]);
        $staff = Employee::query()->create([
            'user_id' => $staffUser->id,
            'employee_number' => 'STF-001',
            'first_name' => 'Staff',
            'last_name' => 'One',
            'email' => $staffUser->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);

        $endorserUser = User::factory()->create(['role' => UserRole::Supervisor]);
        $endorser = Employee::query()->create([
            'user_id' => $endorserUser->id,
            'employee_number' => 'END-001',
            'first_name' => 'End',
            'last_name' => 'Orser',
            'email' => $endorserUser->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);

        $finalUser = User::factory()->create(['role' => UserRole::Supervisor]);
        $final = Employee::query()->create([
            'user_id' => $finalUser->id,
            'employee_number' => 'FIN-001',
            'first_name' => 'Final',
            'last_name' => 'Approver',
            'email' => $finalUser->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);

        return [$staff, $endorser, $final];
    }
}
