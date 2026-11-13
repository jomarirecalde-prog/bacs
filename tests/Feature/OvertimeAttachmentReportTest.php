<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Enums\OvertimeAttachmentReportStatus;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Enums\AccountStatus;
use App\Services\OvertimeAttachmentReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeAttachmentReportTest extends TestCase
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

        Department::query()->create(['name' => 'Operations', 'status' => AccountStatus::Active]);
    }

    public function test_employee_can_submit_attachment_report(): void
    {
        $employee = $this->makeEmployee('OAR-EMP');
        $ot = $this->makeOvertimeRequest($employee);

        $service = app(OvertimeAttachmentReportService::class);
        $report = $service->submit($employee, $employee->user, $this->payload($ot));

        $this->assertSame(OvertimeAttachmentReportStatus::Submitted, $report->status);
        $this->assertDatabaseHas('overtime_attachment_activities', [
            'attachment_report_id' => $report->id,
            'activity_description' => 'Site inspection',
        ]);
    }

    public function test_pdf_route_returns_success(): void
    {
        $employee = $this->makeEmployee('OAR-PDF');
        $ot = $this->makeOvertimeRequest($employee);
        $service = app(OvertimeAttachmentReportService::class);
        $report = $service->submit($employee, $employee->user, $this->payload($ot));

        $this->actingAs($employee->user)
            ->get(route('employee.overtime-attachment.pdf', $report))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    private function makeOvertimeRequest(Employee $employee): OvertimeRequest
    {
        return OvertimeRequest::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => now()->toDateString(),
            'recorded_minutes' => 120,
            'approved_minutes' => 0,
            'status' => 'pending',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(OvertimeRequest $ot): array
    {
        $date = $ot->attendance_date?->toDateString();

        return [
            'action' => 'submit',
            'overtime_request_id' => $ot->id,
            'date_coverage_start' => $date,
            'date_coverage_end' => $date,
            'prepared_by' => $ot->employee?->fullName() ?? 'Test',
            'activities' => [[
                'activity_description' => 'Site inspection',
                'activity_date' => $date,
                'time_from' => '18:00',
                'time_to' => '20:00',
            ]],
        ];
    }

    private function makeEmployee(string $number): Employee
    {
        $user = User::factory()->create(['role' => UserRole::Employee, 'username' => strtolower($number)]);
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => $number,
            'first_name' => 'Test',
            'last_name' => $number,
            'email' => $user->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);

        return $employee->fresh('user');
    }
}
