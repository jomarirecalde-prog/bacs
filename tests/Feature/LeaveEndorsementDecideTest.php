<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\ApprovalTransactionType;
use App\Enums\EmploymentStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\LeaveApplicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ConfiguresCentralApprovalWorkflow;
use Tests\TestCase;

class LeaveEndorsementDecideTest extends TestCase
{
    use ConfiguresCentralApprovalWorkflow;
    use RefreshDatabase;

    public function test_endorser_can_record_decision_via_http_with_signature_upload(): void
    {
        Storage::fake('local');

        [$staff, $endorser, $final] = $this->people();

        $this->resetCentralApproval(
            ApprovalTransactionType::LeaveApplication,
            [$endorser->id],
            $final->id,
        );

        app(LeaveApplicationService::class)->submit($staff, $staff->user, [
            'leave_type' => 'vacation',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
            'reason' => 'Endorsement HTTP test',
            'declaration_accepted' => '1',
            'employee_signature' => 'sig',
        ]);

        $application = LeaveApplication::query()->firstOrFail();
        // Minimal valid 1×1 PNG
        $png = base64_encode(hex2bin('89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789c6300010000050001000d0a2db40000000049454e44ae426082'));

        $this->actingAs($endorser->user)
            ->post(route('leave.approvals.decide', $application), [
                'decision' => 'approved',
                'reason' => 'Endorsed',
                'signature' => 'data:image/png;base64,'.$png,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $application->refresh();
        $this->assertSame(LeaveStatus::PendingCeoFinalApproval, $application->status);
        $this->assertSame(LeaveApprovalStage::CeoFinalApproval, $application->current_stage);

        $assignment = $application->assignments()
            ->where('stage', LeaveApprovalStage::ImmediateSupervisor->value)
            ->where('user_id', $endorser->user_id)
            ->firstOrFail();

        $this->assertNotNull($assignment->signature);
        Storage::disk('local')->assertExists($assignment->signature);
    }

    /** @return array{0: Employee, 1: Employee, 2: Employee} */
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

        return [
            $this->emp('staff', UserRole::Employee),
            $this->emp('endorser', UserRole::Supervisor),
            $this->emp('final', UserRole::Supervisor),
        ];
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
}
