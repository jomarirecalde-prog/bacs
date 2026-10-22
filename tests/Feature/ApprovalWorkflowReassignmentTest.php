<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApprovalTransactionType;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendancePunchType;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\UserRole;
use App\Models\AttendanceCorrectionApprovalAssignment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\CentralApprovalWorkflowService;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class ApprovalWorkflowReassignmentTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    public function test_replacing_endorser_reassigns_pending_pardon_task(): void
    {
        [$department, $staff, $oldEndorser, $newEndorser, $final] = $this->fixtures();

        $this->resetCentralApproval(
            ApprovalTransactionType::Pardon,
            [$oldEndorser->id],
            $final->id,
            departmentId: $department->id,
        );

        $date = ManilaTime::todayDate();
        $correction = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $staff->id,
            'attendance_date' => $date,
            'punch_type' => AttendancePunchType::AmTimeIn->value,
            'requested_value' => ManilaTime::combineDateAndTime($date, '08:05'),
            'reason' => 'Late scan',
            'status' => AttendanceCorrectionStatus::PendingEndorsement->value,
            'current_approval_stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'central_approval_config_id' => \App\Models\ApprovalWorkflowConfiguration::forType(ApprovalTransactionType::Pardon)->id,
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'user_id' => $oldEndorser->user_id,
            'employee_id' => $oldEndorser->id,
            'approver_name' => $oldEndorser->fullName(),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        app(CentralApprovalWorkflowService::class)->update(ApprovalTransactionType::Pardon, $admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'endorser_ids' => [$newEndorser->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $assignment = AttendanceCorrectionApprovalAssignment::query()->first();
        $this->assertSame($newEndorser->user_id, $assignment->user_id);
        $this->assertSame('pending', $assignment->status);

        $this->assertDatabaseHas('approval_workflow_reassignment_logs', [
            'assignable_id' => $correction->id,
            'previous_employee_id' => $oldEndorser->id,
            'new_employee_id' => $newEndorser->id,
        ]);

        $this->assertFalse(
            app(\App\Services\PardonApprovalService::class)->userCanAct($oldEndorser->user, $correction->fresh())
        );
        $this->assertTrue(
            app(\App\Services\PardonApprovalService::class)->userCanAct($newEndorser->user, $correction->fresh())
        );
    }

    public function test_parallel_endorsement_preserves_other_endorser_decision(): void
    {
        [$department, $staff, $oldEndorser, $newEndorser, $peer, $final] = $this->parallelFixtures();

        $this->resetCentralApproval(
            ApprovalTransactionType::Pardon,
            [$oldEndorser->id, $peer->id],
            $final->id,
            departmentId: $department->id,
        );

        $date = ManilaTime::todayDate();
        $correction = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $staff->id,
            'attendance_date' => $date,
            'punch_type' => AttendancePunchType::AmTimeIn->value,
            'requested_value' => ManilaTime::combineDateAndTime($date, '08:05'),
            'reason' => 'Late scan',
            'status' => AttendanceCorrectionStatus::PendingEndorsement->value,
            'current_approval_stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'central_approval_config_id' => \App\Models\ApprovalWorkflowConfiguration::forType(ApprovalTransactionType::Pardon)->id,
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'user_id' => $oldEndorser->user_id,
            'employee_id' => $oldEndorser->id,
            'approver_name' => $oldEndorser->fullName(),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'user_id' => $peer->user_id,
            'employee_id' => $peer->id,
            'approver_name' => $peer->fullName(),
            'status' => 'approved',
            'acted_at' => now(),
            'sort_order' => 1,
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        app(CentralApprovalWorkflowService::class)->update(ApprovalTransactionType::Pardon, $admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'endorser_ids' => [$newEndorser->id, $peer->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $peerRow = AttendanceCorrectionApprovalAssignment::query()
            ->where('employee_id', $peer->id)
            ->first();
        $this->assertSame('approved', $peerRow->status);

        $this->assertSame(1, AttendanceCorrectionApprovalAssignment::query()->where('status', 'pending')->count());
        $this->assertSame($newEndorser->user_id, AttendanceCorrectionApprovalAssignment::query()->where('status', 'pending')->value('user_id'));
    }

    public function test_update_reassigns_pending_tasks_without_extra_confirmation(): void
    {
        [$department, $staff, $oldEndorser, $newEndorser, $final] = $this->fixtures();

        $this->resetCentralApproval(
            ApprovalTransactionType::Pardon,
            [$oldEndorser->id],
            $final->id,
            departmentId: $department->id,
        );

        AttendanceCorrectionRequest::query()->create([
            'employee_id' => $staff->id,
            'attendance_date' => ManilaTime::todayDate(),
            'punch_type' => AttendancePunchType::AmTimeIn->value,
            'requested_value' => ManilaTime::combineDateAndTime(ManilaTime::todayDate(), '08:05'),
            'reason' => 'Late scan',
            'status' => AttendanceCorrectionStatus::PendingEndorsement->value,
            'current_approval_stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'central_approval_config_id' => \App\Models\ApprovalWorkflowConfiguration::forType(ApprovalTransactionType::Pardon)->id,
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => AttendanceCorrectionRequest::query()->value('id'),
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'user_id' => $oldEndorser->user_id,
            'employee_id' => $oldEndorser->id,
            'approver_name' => $oldEndorser->fullName(),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        app(CentralApprovalWorkflowService::class)->update(ApprovalTransactionType::Pardon, $admin, [
            'department_id' => $department->id,
            'endorsement_enabled' => true,
            'final_approval_enabled' => true,
            'endorser_ids' => [$newEndorser->id],
            'final_approver_id' => $final->id,
            'endorsement_mode' => 'parallel',
            'endorsement_parallel_rule' => 'all',
        ]);

        $this->assertSame($newEndorser->user_id, AttendanceCorrectionApprovalAssignment::query()->where('status', 'pending')->value('user_id'));
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
        $oldEndorser = $this->employee('old-end', $department->id, UserRole::Supervisor);
        $newEndorser = $this->employee('new-end', $department->id, UserRole::Supervisor);
        $final = $this->employee('final', $department->id, UserRole::Supervisor);

        return [$department, $staff, $oldEndorser, $newEndorser, $final];
    }

    /** @return array{0: Department, 1: Employee, 2: Employee, 3: Employee, 4: Employee, 5: Employee} */
    private function parallelFixtures(): array
    {
        [$department, $staff, $oldEndorser, $newEndorser, $final] = $this->fixtures();
        $peer = $this->employee('peer', $department->id, UserRole::Supervisor);

        return [$department, $staff, $oldEndorser, $newEndorser, $peer, $final];
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
