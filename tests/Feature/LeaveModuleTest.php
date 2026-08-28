<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveParallelRule;
use App\Enums\LeavePaymentType;
use App\Enums\LeaveStatus;
use App\Enums\UserRole;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowApprover;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\LeaveBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveModuleTest extends TestCase
{
    use RefreshDatabase;

    private WorkSchedule $schedule;

    private Department $department;

    private LeaveApprovalWorkflow $workflow;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = WorkSchedule::query()->create([
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

        $this->department = Department::query()->create([
            'name' => 'Operations',
            'status' => AccountStatus::Active,
        ]);

        $this->workflow = LeaveApprovalWorkflow::query()->where('is_default', true)->firstOrFail();
        $this->workflow->update(['parallel_rule' => LeaveParallelRule::All]);

        $this->ensureCeo();
    }

    private function ensureCeo(): User
    {
        $ceo = User::factory()->create([
            'username' => 'ceo-final',
            'email' => 'ceo-final@bacs.test',
            'name' => 'CEO Approver',
            'role' => UserRole::Supervisor,
        ]);

        Employee::query()->create([
            'user_id' => $ceo->id,
            'employee_number' => 'CEO-001',
            'first_name' => 'CEO',
            'last_name' => 'Approver',
            'email' => $ceo->email,
            'department_id' => $this->department->id,
            'position' => 'CEO / President',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $this->schedule->id,
        ]);

        Setting::put('ceo_user_id', (string) $ceo->id);

        return $ceo->fresh('employee');
    }

    public function test_employee_can_submit_official_leave_application(): void
    {
        $staff = $this->staff();
        $boss = $this->approver('boss1');
        $this->assign($boss, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)
            ->post(route('employee.leave.store'), $this->payload())
            ->assertRedirect();

        $application = LeaveApplication::query()->first();
        $this->assertNotNull($application);
        $this->assertMatchesRegularExpression('/^LF-20\d{2}-\d{4}$/', $application->application_number);
        $this->assertSame(LeaveStatus::PendingSupervisor, $application->status);
        $this->assertSame(3.0, (float) $application->requested_days);
        $this->assertSame($staff->fullName(), $application->employee_print_name);
        $this->assertTrue($application->assignments()->where('user_id', $boss->id)->where('status', 'pending')->exists());
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff->user)
            ->from(route('employee.leave.apply'))
            ->post(route('employee.leave.store'), $this->payload([
                'start_date' => '2026-09-03',
                'end_date' => '2026-09-01',
            ]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_overlapping_applications_are_rejected(): void
    {
        $staff = $this->staff();
        $boss = $this->approver('boss1');
        $this->assign($boss, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload())->assertRedirect();
        $this->actingAs($staff->user)
            ->from(route('employee.leave.apply'))
            ->post(route('employee.leave.store'), $this->payload([
                'start_date' => '2026-09-02',
                'end_date' => '2026-09-04',
            ]))
            ->assertSessionHasErrors('start_date');
    }

    public function test_parallel_all_requires_every_supervisor(): void
    {
        $staff = $this->staff();
        $a = $this->approver('sup-a');
        $b = $this->approver('sup-b');
        $this->assign($a, LeaveApprovalStage::ImmediateSupervisor);
        $this->assign($b, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();

        $this->actingAs($a)->post(route('leave.approvals.decide', $application), [
            'decision' => 'approved',
            'reason' => 'ok',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(LeaveStatus::PendingSupervisor, $application->status);

        $this->actingAs($b)->post(route('leave.approvals.decide', $application), [
            'decision' => 'approved',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(LeaveStatus::PendingCeoFinalApproval, $application->status);

        $ceo = User::query()->where('username', 'ceo-final')->firstOrFail();
        $this->actingAs($ceo)->post(route('leave.approvals.decide', $application), [
            'decision' => 'approved',
        ])->assertRedirect();

        $this->assertSame(LeaveStatus::PendingHr, $application->fresh()->status);
    }

    public function test_parallel_any_advances_after_one_approval(): void
    {
        $this->workflow->update(['parallel_rule' => LeaveParallelRule::Any]);
        $staff = $this->staff();
        $a = $this->approver('sup-a');
        $b = $this->approver('sup-b');
        $this->assign($a, LeaveApprovalStage::ImmediateSupervisor);
        $this->assign($b, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();

        $this->actingAs($a)->post(route('leave.approvals.decide', $application), [
            'decision' => 'approved',
        ])->assertRedirect();

        $this->assertSame(LeaveStatus::PendingCeoFinalApproval, $application->fresh()->status);
    }

    public function test_parallel_all_denial_is_recorded_and_does_not_delete_the_application(): void
    {
        $staff = $this->staff();
        $a = $this->approver('sup-a');
        $b = $this->approver('sup-b');
        $this->assign($a, LeaveApprovalStage::ImmediateSupervisor);
        $this->assign($b, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();

        $this->actingAs($a)
            ->from(route('leave.approvals.show', $application))
            ->post(route('leave.approvals.decide', $application), [
                'decision' => 'denied',
            ])
            ->assertSessionHasErrors('reason');

        $this->actingAs($a)->post(route('leave.approvals.decide', $application), [
            'decision' => 'denied',
            'reason' => 'Coverage needed on site',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(LeaveStatus::Denied, $application->status);
        $this->assertDatabaseHas('leave_applications', ['id' => $application->id]);
        $this->assertDatabaseHas('leave_approval_actions', [
            'leave_application_id' => $application->id,
            'decision' => 'denied',
        ]);
        $this->assertEquals(0, (float) (LeaveBalance::query()->where('employee_id', $staff->id)->where('leave_type_code', 'vacation')->value('used_days') ?? 0));
    }

    public function test_employee_cannot_approve_own_leave(): void
    {
        $staff = $this->staff();
        $this->assign($staff->user, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();

        $this->assertFalse($staff->user->can('approve', $application));
        $this->actingAs($staff->user)
            ->post(route('leave.approvals.decide', $application), ['decision' => 'approved'])
            ->assertForbidden();
    }

    public function test_employee_cannot_view_another_employees_application(): void
    {
        $staff = $this->staff();
        $other = $this->staff('other');
        $boss = $this->approver('boss1');
        $this->assign($boss, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();

        $this->actingAs($other->user)
            ->get(route('employee.leave.show', $application))
            ->assertForbidden();
    }

    public function test_hr_approval_deducts_balance_and_marks_dtr_on_leave(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'hr-admin']);
        $staff = $this->staff();
        $boss = $this->approver('boss1');
        $ceo = User::query()->where('username', 'ceo-final')->firstOrFail();
        $this->assign($boss, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();

        $this->actingAs($boss)->post(route('leave.approvals.decide', $application), [
            'decision' => 'approved',
        ]);

        $this->actingAs($ceo)->post(route('leave.approvals.decide', $application), [
            'decision' => 'approved',
        ]);

        $this->assertSame(0.0, (float) (LeaveBalance::query()->where('employee_id', $staff->id)->where('leave_type_code', 'vacation')->value('used_days') ?: 0));

        $this->actingAs($admin)->post(route('admin.leave.hr', $application), [
            'decision' => 'approved',
            'payment_type' => LeavePaymentType::WithPay->value,
            'hr_sil_as_of' => '2026-09-01',
        ])->assertRedirect();

        $application->refresh();
        $this->assertSame(LeaveStatus::Approved, $application->status);
        $this->assertSame(3.0, (float) LeaveBalance::query()->where('employee_id', $staff->id)->where('leave_type_code', 'vacation')->value('used_days'));
        $this->assertDatabaseHas('leaves', [
            'employee_id' => $staff->id,
            'status' => 'approved',
        ]);
        $this->assertSame(
            AttendanceStatus::OnLeave,
            Attendance::query()->where('employee_id', $staff->id)->whereDate('attendance_date', '2026-09-01')->first()?->status
        );
    }

    public function test_existing_time_entry_is_flagged_instead_of_deleted(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'hr-admin']);
        $staff = $this->staff();
        $boss = $this->approver('boss1');
        $ceo = User::query()->where('username', 'ceo-final')->firstOrFail();
        $this->assign($boss, LeaveApprovalStage::ImmediateSupervisor);

        Attendance::query()->create([
            'employee_id' => $staff->id,
            'attendance_date' => '2026-09-01',
            'time_in' => '2026-09-01 08:00:00',
            'time_out' => '2026-09-01 17:00:00',
            'status' => AttendanceStatus::Present,
        ]);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();
        $this->actingAs($boss)->post(route('leave.approvals.decide', $application), ['decision' => 'approved']);
        $this->actingAs($ceo)->post(route('leave.approvals.decide', $application), ['decision' => 'approved']);
        $this->actingAs($admin)->post(route('admin.leave.hr', $application), [
            'decision' => 'approved',
            'payment_type' => LeavePaymentType::WithPay->value,
        ]);

        $application->refresh();
        $this->assertTrue($application->attendance_conflict);
        $this->assertSame('08:00:00', optional(Attendance::query()->where('employee_id', $staff->id)->whereDate('attendance_date', '2026-09-01')->first()?->time_in)->format('H:i:s'));
        $this->assertTrue(
            $application->conflicts()->whereDate('attendance_date', '2026-09-01')->exists()
        );
    }

    public function test_employee_cannot_open_admin_leave_management(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff->user)->get(route('admin.leave.index'))->assertForbidden();
        $this->actingAs($staff->user)->get(route('admin.leave.workflow'))->assertForbidden();
        $this->actingAs($staff->user)->get(route('admin.leave.entitlements'))->assertForbidden();
    }

    public function test_manual_adjustment_only_affects_target_employee(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'balance-admin']);
        $employeeA = $this->staff('emp-a');
        $employeeB = $this->staff('emp-b');
        $service = app(LeaveBalanceService::class);

        $service->initializeForEmployee($employeeA);
        $service->initializeForEmployee($employeeB);

        $this->actingAs($admin)->post(route('admin.leave.entitlements.adjustments.store', $employeeA), [
            'leave_type_code' => 'vacation',
            'adjustment_kind' => 'add',
            'days' => 2,
            'reason' => 'Service incentive credit',
            'effective_date' => '2026-01-01',
            'authorized_by_name' => 'HR Admin',
            'confirm' => '1',
        ])->assertRedirect();

        $balanceA = LeaveBalance::query()->where('employee_id', $employeeA->id)->where('leave_type_code', 'vacation')->first();
        $balanceB = LeaveBalance::query()->where('employee_id', $employeeB->id)->where('leave_type_code', 'vacation')->first();

        $this->assertSame(7.0, (float) $balanceA->entitled_days);
        $this->assertSame(5.0, (float) $balanceB->entitled_days);
        $this->assertDatabaseHas('leave_balance_adjustments', [
            'employee_id' => $employeeA->id,
            'leave_type_code' => 'vacation',
            'action_type' => 'manual_addition',
        ]);
    }

    public function test_hr_deduction_is_idempotent(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'hr-admin2']);
        $staff = $this->staff();
        $service = app(LeaveBalanceService::class);
        $service->initializeForEmployee($staff);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload(['start_date' => '2026-09-01', 'end_date' => '2026-09-01']));
        $application = LeaveApplication::query()->first();

        $service->deductForApplication($application, $admin, 1.0);
        $service->deductForApplication($application, $admin, 1.0);

        $this->assertSame(1.0, (float) LeaveBalance::query()->where('employee_id', $staff->id)->where('leave_type_code', 'vacation')->value('used_days'));
        $this->assertSame(1, LeaveBalanceAdjustment::query()->where('leave_application_id', $application->id)->where('action_type', 'approved_leave_deduction')->count());
    }

    public function test_employee_can_view_own_leave_balances(): void
    {
        $staff = $this->staff();
        app(LeaveBalanceService::class)->initializeForEmployee($staff);

        $this->actingAs($staff->user)->get(route('employee.leave.balances'))->assertOk();
        $this->actingAs($staff->user)->get(route('employee.leave.balances.adjustments'))->assertOk();
    }

    public function test_admin_can_access_employee_leave_balances_index(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'leave-admin']);

        $this->actingAs($admin)->get(route('admin.leave.entitlements'))->assertOk();
    }

    public function test_unassigned_approver_cannot_decide(): void
    {
        $staff = $this->staff();
        $boss = $this->approver('boss1');
        $stranger = $this->approver('stranger');
        $this->assign($boss, LeaveApprovalStage::ImmediateSupervisor);

        $this->actingAs($staff->user)->post(route('employee.leave.store'), $this->payload());
        $application = LeaveApplication::query()->first();

        $this->actingAs($stranger)
            ->post(route('leave.approvals.decide', $application), ['decision' => 'approved'])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'leave_type' => 'vacation',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'reason' => 'Family matter in Puerto Princesa',
            'declaration_accepted' => '1',
            'employee_signature' => 'signed',
        ], $overrides);
    }

    private function staff(string $username = 'staff'): Employee
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $username.'@bacs.test',
            'name' => ucfirst($username).' Employee',
            'role' => UserRole::Employee,
        ]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($username).'-001',
            'first_name' => ucfirst($username),
            'last_name' => 'Employee',
            'email' => $user->email,
            'department_id' => $this->department->id,
            'position' => 'Field Staff',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $this->schedule->id,
        ]);
    }

    private function approver(string $username): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $username.'@bacs.test',
            'name' => ucfirst($username).' Approver',
            'role' => UserRole::Supervisor,
        ]);

        Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($username).'-SUP',
            'first_name' => ucfirst($username),
            'last_name' => 'Approver',
            'email' => $user->email,
            'department_id' => $this->department->id,
            'position' => 'Supervisor',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $this->schedule->id,
        ]);

        return $user->fresh('employee');
    }

    private function assign(User $user, LeaveApprovalStage $stage): void
    {
        LeaveApprovalWorkflowApprover::query()->create([
            'workflow_id' => $this->workflow->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'sort_order' => (int) LeaveApprovalWorkflowApprover::query()->where('workflow_id', $this->workflow->id)->count(),
        ]);
    }
}
