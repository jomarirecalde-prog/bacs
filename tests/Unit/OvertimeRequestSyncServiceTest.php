<?php

namespace Tests\Unit;

use App\Enums\AccountStatus;
use App\Enums\AttendanceStatus;
use App\Enums\EmploymentStatus;
use App\Enums\OvertimeAttachmentReportStatus;
use App\Enums\OvertimeRequestStatus;
use App\Models\Attendance;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OvertimeAttachmentReport;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Payroll\OvertimeRequestSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OvertimeRequestSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_recorded_minutes_follow_ot_attachment_declared_duration_not_clock_overtime(): void
    {
        $employee = $this->makeEmployee('sync');

        $attendance = Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-08-11',
            'overtime_minutes' => 480,
            'status' => AttendanceStatus::Overtime,
        ]);

        $request = OvertimeRequest::query()->create([
            'employee_id' => $employee->id,
            'attendance_id' => $attendance->id,
            'attendance_date' => '2026-08-11',
            'recorded_minutes' => 480,
            'approved_minutes' => 480,
            'status' => OvertimeRequestStatus::Approved,
        ]);

        OvertimeAttachmentReport::query()->create([
            'overtime_request_id' => $request->id,
            'employee_id' => $employee->id,
            'department_id' => $employee->department_id,
            'report_no' => 'OT-SYNC-001',
            'date_coverage_start' => '2026-08-11',
            'date_coverage_end' => '2026-08-11',
            'status' => OvertimeAttachmentReportStatus::Submitted->value,
            'total_activity_minutes' => 120,
            'prepared_by' => 'Test',
        ]);

        app(OvertimeRequestSyncService::class)->syncForEmployee(
            $employee,
            \Illuminate\Support\Carbon::parse('2026-08-11'),
            \Illuminate\Support\Carbon::parse('2026-08-11'),
        );

        $request->refresh();
        $this->assertSame(120, $request->recorded_minutes);
        $this->assertSame(120, $request->approved_minutes);
    }

    private function makeEmployee(string $username): Employee
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

        $department = Department::query()->create([
            'name' => 'IT',
            'status' => AccountStatus::Active,
        ]);

        $user = User::factory()->create([
            'username' => $username,
            'email' => $username.'@bacs.test',
        ]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($username).'-001',
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $user->email,
            'department_id' => $department->id,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => 1,
        ]);
    }
}
