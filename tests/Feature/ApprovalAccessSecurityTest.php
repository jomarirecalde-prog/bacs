<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\OvertimeRequestStatus;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeApprovalAssignment;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalAccessSecurityTest extends TestCase
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

        Department::query()->create(['name' => 'Ops', 'status' => AccountStatus::Active]);
    }

    public function test_stranger_cannot_view_overtime_approval_detail(): void
    {
        $subject = $this->employee('subject');
        $stranger = $this->employee('stranger');
        $ot = OvertimeRequest::query()->create([
            'employee_id' => $subject->id,
            'attendance_date' => now()->toDateString(),
            'recorded_minutes' => 60,
            'status' => OvertimeRequestStatus::PendingEndorsement,
            'current_approval_stage' => LeaveApprovalStage::ImmediateSupervisor->value,
        ]);

        $this->actingAs($stranger->user)
            ->get(route('overtime.approvals.show', $ot))
            ->assertForbidden();
    }

    public function test_assigned_approver_can_view_overtime_approval_detail(): void
    {
        $subject = $this->employee('ot-subject');
        $approver = $this->employee('ot-boss');
        $ot = OvertimeRequest::query()->create([
            'employee_id' => $subject->id,
            'attendance_date' => now()->toDateString(),
            'recorded_minutes' => 60,
            'status' => OvertimeRequestStatus::PendingEndorsement,
            'current_approval_stage' => LeaveApprovalStage::ImmediateSupervisor->value,
        ]);

        OvertimeApprovalAssignment::query()->create([
            'overtime_request_id' => $ot->id,
            'user_id' => $approver->user_id,
            'employee_id' => $approver->id,
            'stage' => LeaveApprovalStage::ImmediateSupervisor->value,
            'approver_name' => $approver->fullName(),
            'status' => 'pending',
        ]);

        $this->actingAs($approver->user)
            ->get(route('overtime.approvals.show', $ot))
            ->assertOk();
    }

    public function test_stranger_cannot_view_pardon_approval_detail(): void
    {
        $subject = $this->employee('p-subject');
        $stranger = $this->employee('p-stranger');
        $correction = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $subject->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceCorrectionStatus::PendingEndorsement,
            'current_approval_stage' => LeaveApprovalStage::ImmediateSupervisor,
            'reason' => 'Forgot punch',
            'punch_type' => 'am_time_in',
            'requested_value' => now(),
        ]);

        $this->actingAs($stranger->user)
            ->get(route('pardon.approvals.show', $correction))
            ->assertForbidden();
    }

    private function employee(string $slug): Employee
    {
        $user = User::factory()->create(['username' => $slug]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($slug),
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
    }
}
