<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApprovalAssigneeType;
use App\Enums\ApprovalTransactionType;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\CentralApprovalWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class UnifiedApprovalWorkflowTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    public function test_unified_update_applies_same_department_assignees_to_all_transaction_types(): void
    {
        [$department, , $endorser, , $final] = $this->fixtures();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->resetCentralApproval(ApprovalTransactionType::LeaveApplication, [$endorser->id], $final->id, departmentId: $department->id);
        $this->resetCentralApproval(ApprovalTransactionType::Pardon, [], null, finalEnabled: false, departmentId: $department->id);

        $service = app(CentralApprovalWorkflowService::class);
        $service->updateUnified($admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'endorser_ids' => [$endorser->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        foreach (ApprovalTransactionType::cases() as $type) {
            $config = ApprovalWorkflowConfiguration::forType($type);
            $assignees = $config->assignees()->where('department_id', $department->id)->get();

            $this->assertSame(
                [$endorser->id],
                $assignees->where('approval_type', ApprovalAssigneeType::Endorser)->pluck('employee_id')->all(),
                $type->value.' endorsers mismatch',
            );
            $this->assertSame(
                $final->id,
                $assignees->where('approval_type', ApprovalAssigneeType::FinalApprover)->value('employee_id'),
                $type->value.' final approver mismatch',
            );
        }
    }

    public function test_unified_update_preserves_first_endorsers_when_endorser_ids_array_is_empty(): void
    {
        [$department, , $endorser, $secondaryEndorser, $final] = $this->fixtures();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $service = app(CentralApprovalWorkflowService::class);
        $service->updateUnified($admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'endorser_ids' => [$endorser->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $service->updateUnified($admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'secondary_endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'endorser_ids' => [],
            'secondary_endorser_ids' => [$secondaryEndorser->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $config = ApprovalWorkflowConfiguration::forType(ApprovalTransactionType::LeaveApplication);
        $assignees = $config->assignees()->where('department_id', $department->id)->get();

        $this->assertSame(
            [$endorser->id],
            $assignees->where('approval_type', ApprovalAssigneeType::Endorser)->pluck('employee_id')->all(),
        );
    }

    public function test_unified_update_preserves_first_endorsers_when_only_second_endorsement_is_submitted(): void
    {
        [$department, , $endorser, $secondaryEndorser, $final] = $this->fixtures();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $service = app(CentralApprovalWorkflowService::class);
        $service->updateUnified($admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'endorser_ids' => [$endorser->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $service->updateUnified($admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'secondary_endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'secondary_endorser_ids' => [$secondaryEndorser->id],
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $config = ApprovalWorkflowConfiguration::forType(ApprovalTransactionType::LeaveApplication);
        $assignees = $config->assignees()->where('department_id', $department->id)->get();

        $this->assertSame(
            [$endorser->id],
            $assignees->where('approval_type', ApprovalAssigneeType::Endorser)->pluck('employee_id')->all(),
        );
        $this->assertSame(
            [$secondaryEndorser->id],
            $assignees->where('approval_type', ApprovalAssigneeType::SecondaryEndorser)->pluck('employee_id')->all(),
        );
        $this->assertSame(
            $final->id,
            $assignees->where('approval_type', ApprovalAssigneeType::FinalApprover)->value('employee_id'),
        );
    }

    public function test_admin_can_save_unified_workflow_from_settings(): void
    {
        [$department, , $endorser, , $final] = $this->fixtures();
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->put(route('admin.settings.approval-workflow.update', 'unified'), [
            'department_id' => $department->id,
            'endorsement_enabled' => '1',
            'final_approval_enabled' => '1',
            'endorser_ids' => [$endorser->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $pardon = ApprovalWorkflowConfiguration::forType(ApprovalTransactionType::Pardon);
        $this->assertTrue(
            $pardon->assignees()
                ->where('department_id', $department->id)
                ->where('approval_type', ApprovalAssigneeType::Endorser->value)
                ->where('employee_id', $endorser->id)
                ->exists()
        );
    }

    /** @return array{0: Department, 1: Employee, 2: Employee, 3: Employee, 4: Employee} */
    private function fixtures(): array
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

        $department = Department::query()->create(['name' => 'Ops', 'status' => AccountStatus::Active]);
        $staff = $this->employee('staff', $department->id);
        $endorser = $this->employee('endorser', $department->id, UserRole::Supervisor);
        $spare = $this->employee('spare', $department->id, UserRole::Supervisor);
        $final = $this->employee('final', $department->id, UserRole::Supervisor);

        return [$department, $staff, $endorser, $spare, $final];
    }

    private function employee(string $key, int $departmentId, UserRole $role = UserRole::Employee): Employee
    {
        $user = User::factory()->create(['role' => $role, 'username' => $key]);

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
}
