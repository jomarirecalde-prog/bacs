<?php

namespace Tests\Feature;

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
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeOfficialDtrTest extends TestCase
{
    use RefreshDatabase;

    private WorkSchedule $schedule;

    private Department $department;

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
            'name' => 'Admin',
            'status' => AccountStatus::Active,
        ]);
    }

    public function test_employee_dtr_page_shows_am_pm_columns_from_real_attendance(): void
    {
        $employee = $this->makeEmployee('ana');

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-08-11',
            'time_in' => ManilaTime::combineDateAndTime('2026-08-11', '08:00'),
            'time_out' => ManilaTime::combineDateAndTime('2026-08-11', '18:00'),
            'total_minutes' => 540,
            'overtime_minutes' => 60,
            'status' => AttendanceStatus::Overtime,
        ]);

        $this->actingAs($employee->user)
            ->get(route('employee.dtr', ['period' => 'c:2026-08-11_2026-08-25']))
            ->assertOk()
            ->assertSee('My Daily Time Record')
            ->assertSee('AM Time In')
            ->assertSee('AM Time Out')
            ->assertSee('PM Time In')
            ->assertSee('PM Time Out')
            ->assertSee('Overtime')
            ->assertSee('Total Hours')
            ->assertSee('08/11/2026')
            ->assertSee('Tuesday')
            ->assertSee('8:00 AM')
            ->assertSee('12:00 PM')
            ->assertSee('1:00 PM')
            ->assertSee('6:00 PM')
            ->assertSee($employee->fullName())
            ->assertSee('Admin');
    }

    public function test_employee_cannot_view_or_download_another_employee_dtr_by_id(): void
    {
        $a = $this->makeEmployee('alpha');
        $b = $this->makeEmployee('beta');

        $this->actingAs($a->user)
            ->get(route('employee.dtr', ['employee_id' => $b->id, 'period' => 'c:2026-08-11_2026-08-25']))
            ->assertForbidden();

        $this->actingAs($a->user)
            ->get(route('employee.dtr.export', ['employee_id' => $b->id, 'format' => 'pdf']))
            ->assertForbidden();

        $this->actingAs($a->user)
            ->get(route('employee.dtr.print', ['employee_id' => $b->id]))
            ->assertForbidden();
    }

    public function test_guest_cannot_download_official_dtr(): void
    {
        $this->get(route('employee.dtr.export', ['format' => 'pdf']))
            ->assertRedirect(route('login'));
    }

    public function test_download_pdf_uses_logged_in_employee_and_returns_pdf(): void
    {
        $employee = $this->makeEmployee('carlo');

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-08-12',
            'time_in' => ManilaTime::combineDateAndTime('2026-08-12', '08:00'),
            'time_out' => ManilaTime::combineDateAndTime('2026-08-12', '17:00'),
            'total_minutes' => 480,
            'status' => AttendanceStatus::Present,
        ]);

        $response = $this->actingAs($employee->user)
            ->get(route('employee.dtr.export', [
                'period' => 'c:2026-08-11_2026-08-25',
                'format' => 'pdf',
            ]));

        $response->assertOk();
        $this->assertStringStartsWith('application/pdf', (string) $response->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_employee_dtr_uses_ot_attachment_declared_duration_not_inflated_clock_ot(): void
    {
        $employee = $this->makeEmployee('otoff');

        Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-08-11',
            'time_in' => ManilaTime::combineDateAndTime('2026-08-11', '08:00'),
            'time_out' => ManilaTime::combineDateAndTime('2026-08-11', '17:00'),
            'total_minutes' => 480,
            'overtime_minutes' => 480,
            'status' => AttendanceStatus::Overtime,
        ]);

        $ot = OvertimeRequest::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-08-11',
            'recorded_minutes' => 480,
            'approved_minutes' => 0,
            'status' => OvertimeRequestStatus::Pending,
        ]);

        OvertimeAttachmentReport::query()->create([
            'overtime_request_id' => $ot->id,
            'employee_id' => $employee->id,
            'department_id' => $this->department->id,
            'report_no' => 'OT-DTR-001',
            'date_coverage_start' => '2026-08-11',
            'date_coverage_end' => '2026-08-11',
            'status' => OvertimeAttachmentReportStatus::Submitted->value,
            'total_activity_minutes' => 120,
            'prepared_by' => $employee->fullName(),
        ]);

        $this->actingAs($employee->user)
            ->get(route('employee.dtr', ['period' => 'c:2026-08-11_2026-08-25']))
            ->assertOk()
            ->assertSee('2 hours', false);
    }

    public function test_employee_dtr_shows_payroll_approved_overtime_not_only_recorded(): void
    {
        $employee = $this->makeEmployee('otpay');

        $attendance = Attendance::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => '2026-08-11',
            'time_in' => ManilaTime::combineDateAndTime('2026-08-11', '08:00'),
            'time_out' => ManilaTime::combineDateAndTime('2026-08-11', '19:00'),
            'total_minutes' => 600,
            'overtime_minutes' => 120,
            'status' => AttendanceStatus::Overtime,
        ]);

        OvertimeRequest::query()->create([
            'employee_id' => $employee->id,
            'attendance_id' => $attendance->id,
            'attendance_date' => '2026-08-11',
            'recorded_minutes' => 120,
            'approved_minutes' => 90,
            'status' => OvertimeRequestStatus::Approved,
        ]);

        $this->actingAs($employee->user)
            ->get(route('employee.dtr', ['period' => 'c:2026-08-11_2026-08-25']))
            ->assertOk()
            ->assertSee('1 hr 30 min', false);
    }

    public function test_month_and_year_query_still_renders_the_full_month(): void
    {
        $employee = $this->makeEmployee('dora');

        $this->actingAs($employee->user)
            ->get(route('employee.dtr', ['month' => 8, 'year' => 2026]))
            ->assertOk()
            ->assertSee('08/31/2026');
    }

    private function makeEmployee(string $username): Employee
    {
        $user = User::factory()->create([
            'username' => $username,
            'email' => $username.'@bacs.test',
            'name' => ucfirst($username).' Test',
        ]);

        return Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => strtoupper($username).'-001',
            'first_name' => ucfirst($username),
            'last_name' => 'Test',
            'email' => $user->email,
            'department_id' => $this->department->id,
            'position' => 'Staff',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $this->schedule->id,
        ]);
    }
}
