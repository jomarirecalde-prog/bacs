<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApprovalTransactionType;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\LeaveApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class DepartmentBasedApprovalWorkflowTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    public function test_leave_routes_to_department_endorser_only(): void
    {
        [$deptA, $deptB, $staffA, $staffB, $endorserA, $endorserB, $finalA] = $this->departmentFixtures();

        $this->resetCentralApproval(ApprovalTransactionType::LeaveApplication, [$endorserA->id], $finalA->id, departmentId: $deptA->id);
        $this->resetCentralApproval(ApprovalTransactionType::LeaveApplication, [$endorserB->id], null, departmentId: $deptB->id);

        app(LeaveApplicationService::class)->submit($staffA, $staffA->user, $this->leavePayload());
        app(LeaveApplicationService::class)->submit($staffB, $staffB->user, $this->leavePayload('2026-10-02'));

        $pendingA = app(LeaveApplicationService::class)->pendingFor($endorserA->user)->pluck('employee_id');
        $pendingB = app(LeaveApplicationService::class)->pendingFor($endorserB->user)->pluck('employee_id');

        $this->assertTrue($pendingA->contains($staffA->id));
        $this->assertFalse($pendingA->contains($staffB->id));
        $this->assertTrue($pendingB->contains($staffB->id));
        $this->assertFalse($pendingB->contains($staffA->id));
    }

    public function test_missing_department_approver_marks_pending_assignment(): void
    {
        [$deptA, , $staffA, , , , ] = $this->departmentFixtures();

        $this->resetCentralApproval(ApprovalTransactionType::LeaveApplication, [], null, departmentId: $deptA->id);

        app(LeaveApplicationService::class)->submit($staffA, $staffA->user, $this->leavePayload());

        $application = \App\Models\LeaveApplication::query()->firstOrFail();
        $this->assertSame(LeaveStatus::PendingApproverAssignment, $application->status);
    }

    /** @return array{0: Department, 1: Department, 2: Employee, 3: Employee, 4: Employee, 5: Employee, 6: Employee} */
    private function departmentFixtures(): array
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

        $deptA = Department::query()->create(['name' => 'Dept A', 'status' => AccountStatus::Active]);
        $deptB = Department::query()->create(['name' => 'Dept B', 'status' => AccountStatus::Active]);

        $staffA = $this->employeeForDepartment('staff-a', $deptA->id);
        $staffB = $this->employeeForDepartment('staff-b', $deptB->id);
        $endorserA = $this->employeeForDepartment('end-a', $deptA->id, UserRole::Supervisor);
        $endorserB = $this->employeeForDepartment('end-b', $deptB->id, UserRole::Supervisor);
        $finalA = $this->employeeForDepartment('final-a', $deptA->id, UserRole::Supervisor);

        return [$deptA, $deptB, $staffA, $staffB, $endorserA, $endorserB, $finalA];
    }

    private function employeeForDepartment(string $key, int $departmentId, UserRole $role = UserRole::Employee): Employee
    {
        $user = User::factory()->create(['role' => $role]);
        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($key),
            'first_name' => ucfirst($key),
            'last_name' => 'User',
            'email' => $user->email,
            'department_id' => $departmentId,
            'employment_status' => EmploymentStatus::Regular,
        ]);
    }

    /** @return array<string, mixed> */
    private function leavePayload(string $date = '2026-10-01'): array
    {
        return [
            'leave_type' => 'vacation',
            'start_date' => $date,
            'end_date' => $date,
            'reason' => 'Department routing test',
            'declaration_accepted' => '1',
            'employee_signature' => 'sig',
        ];
    }
}
