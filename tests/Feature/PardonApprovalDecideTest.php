<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApprovalTransactionType;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendancePunchType;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionApprovalAssignment;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class PardonApprovalDecideTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    public function test_endorser_can_approve_pardon_request(): void
    {
        [$department, $staff, $endorser, $final] = $this->fixtures();

        $this->resetCentralApproval(
            ApprovalTransactionType::Pardon,
            [$endorser->id],
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
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'user_id' => $endorser->user_id,
            'employee_id' => $endorser->id,
            'approver_name' => $endorser->fullName(),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::CeoFinalApproval->value,
            'user_id' => $final->user_id,
            'employee_id' => $final->id,
            'approver_name' => $final->fullName(),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $this->actingAs($endorser->user)
            ->post(route('pardon.approvals.decide', $correction), ['decision' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $correction->refresh();
        $this->assertSame(AttendanceCorrectionStatus::PendingFinalApproval, $correction->status);
        $this->assertSame(LeaveApprovalStage::CeoFinalApproval, $correction->current_approval_stage);
    }

    public function test_unauthorized_user_cannot_decide_pardon_request(): void
    {
        [$department, $staff, $endorser, $final] = $this->fixtures();

        $this->resetCentralApproval(
            ApprovalTransactionType::Pardon,
            [$endorser->id],
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
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'user_id' => $endorser->user_id,
            'employee_id' => $endorser->id,
            'approver_name' => $endorser->fullName(),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $intruder = $this->employee('intruder', $department->id);

        $this->actingAs($intruder->user)
            ->post(route('pardon.approvals.decide', $correction), ['decision' => 'approved'])
            ->assertForbidden();
    }

    public function test_final_approver_can_fully_approve_pardon_request(): void
    {
        [$department, $staff, $endorser, $final] = $this->fixtures();

        $this->resetCentralApproval(
            ApprovalTransactionType::Pardon,
            [$endorser->id],
            $final->id,
            departmentId: $department->id,
        );

        $date = ManilaTime::todayDate();
        Attendance::query()->create([
            'employee_id' => $staff->id,
            'attendance_date' => $date,
            'am_time_in' => ManilaTime::combineDateAndTime($date, '08:25'),
            'status' => 'late',
        ]);

        $correction = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $staff->id,
            'attendance_date' => $date,
            'punch_type' => AttendancePunchType::AmTimeIn->value,
            'original_value' => ManilaTime::combineDateAndTime($date, '08:25'),
            'requested_value' => ManilaTime::combineDateAndTime($date, '08:05'),
            'reason' => 'Late scan',
            'status' => AttendanceCorrectionStatus::PendingFinalApproval->value,
            'current_approval_stage' => LeaveApprovalStage::CeoFinalApproval->value,
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'user_id' => $endorser->user_id,
            'employee_id' => $endorser->id,
            'approver_name' => $endorser->fullName(),
            'status' => 'approved',
            'sort_order' => 0,
            'acted_at' => now(),
        ]);

        AttendanceCorrectionApprovalAssignment::query()->create([
            'attendance_correction_request_id' => $correction->id,
            'stage' => LeaveApprovalStage::CeoFinalApproval->value,
            'user_id' => $final->user_id,
            'employee_id' => $final->id,
            'approver_name' => $final->fullName(),
            'status' => 'pending',
            'sort_order' => 0,
        ]);

        $this->actingAs($final->user)
            ->post(route('pardon.approvals.decide', $correction), ['decision' => 'approved'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $correction->refresh();
        $this->assertSame(AttendanceCorrectionStatus::Approved, $correction->status);
        $record = Attendance::query()->where('employee_id', $staff->id)->first();
        $this->assertSame('08:05', $record->am_time_in->format('H:i'));
    }

    /** @return array{0: Department, 1: Employee, 2: Employee, 3: Employee} */
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
        $final = $this->employee('final', $department->id, UserRole::Supervisor);

        return [$department, $staff, $endorser, $final];
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
