<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\AttendancePunchType;
use App\Enums\EmploymentStatus;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\AttendanceStation;
use App\Services\EmployeeQrService;
use App\Services\StationBindingService;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCorrectionTest extends TestCase
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
            'name' => 'Operations',
            'status' => AccountStatus::Active,
        ]);
    }

    public function test_employee_can_submit_correction_for_specific_field(): void
    {
        $employee = $this->makeEmployee('correction');
        $date = ManilaTime::todayDate();

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->actingAs($employee->user)->postJson(route('attendance.time-in'))->assertOk();

        $this->actingAs($employee->user)
            ->post(route('employee.attendance-corrections.store'), [
                'attendance_date' => $date,
                'punch_type' => AttendancePunchType::AmTimeIn->value,
                'requested_time' => '08:05',
                'reason' => 'I scanned late because the station camera was restarting.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_correction_requests', [
            'employee_id' => $employee->id,
            'punch_type' => AttendancePunchType::AmTimeIn->value,
            'status' => AttendanceCorrectionStatus::Pending->value,
        ]);
    }

    public function test_admin_approval_updates_only_requested_field(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('approve');
        $date = ManilaTime::todayDate();

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->actingAs($employee->user)->postJson(route('attendance.time-in'))->assertOk();
        $this->travelTo(ManilaTime::combineDateAndTime($date, '12:00'));
        $this->actingAs($employee->user)->postJson(route('attendance.time-out'))->assertOk();

        $request = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $employee->id,
            'attendance_id' => Attendance::query()->first()->id,
            'attendance_date' => $date,
            'punch_type' => AttendancePunchType::PmTimeOut->value,
            'original_value' => null,
            'requested_value' => ManilaTime::combineDateAndTime($date, '17:30'),
            'reason' => 'Forgot to scan PM Time Out at the station.',
            'status' => AttendanceCorrectionStatus::Pending->value,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.attendance-corrections.review', $request), [
                'decision' => 'approve',
                'review_notes' => 'Verified with supervisor.',
            ])
            ->assertRedirect();

        $record = Attendance::query()->first()->fresh();
        $this->assertNotNull($record->am_time_in);
        $this->assertNotNull($record->am_time_out);
        $this->assertNotNull($record->pm_time_out);
        $this->assertSame('17:30', $record->pm_time_out->format('H:i'));
    }

    public function test_pending_correction_blocks_station_scan_for_today(): void
    {
        $employee = $this->makeEmployee('blocked');
        $date = ManilaTime::todayDate();
        $token = app(EmployeeQrService::class)->issue($employee);

        AttendanceCorrectionRequest::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'punch_type' => AttendancePunchType::AmTimeIn->value,
            'requested_value' => ManilaTime::combineDateAndTime($date, '08:05'),
            'reason' => 'Station did not record my AM Time In properly today.',
            'status' => AttendanceCorrectionStatus::Pending->value,
        ]);

        $station = AttendanceStation::factory()->create(['password' => 'station-pass']);
        $response = $this->post(route('station.login.store'), [
            'station_id' => $station->station_code,
            'password' => 'station-pass',
        ]);
        $response->assertRedirect(route('station.dashboard'));
        $cookie = $response->getCookie(StationBindingService::COOKIE, decrypt: true);
        if ($cookie) {
            $this->withCookie(StationBindingService::COOKIE, $cookie->getValue());
        }
        $this->withCredentials();
        $this->actingAs($station->fresh(), 'station');

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'PENDING_CORRECTION')
            ->assertJsonPath('ok', false);
    }

    public function test_employee_can_cancel_pending_request(): void
    {
        $employee = $this->makeEmployee('cancel');
        $date = ManilaTime::todayDate();

        $request = AttendanceCorrectionRequest::query()->create([
            'employee_id' => $employee->id,
            'attendance_date' => $date,
            'punch_type' => AttendancePunchType::PmTimeIn->value,
            'requested_value' => ManilaTime::combineDateAndTime($date, '13:05'),
            'reason' => 'Need to correct PM Time In after lunch meeting.',
            'status' => AttendanceCorrectionStatus::Pending->value,
        ]);

        $this->actingAs($employee->user)
            ->post(route('employee.attendance-corrections.cancel', $request))
            ->assertRedirect();

        $this->assertSame(AttendanceCorrectionStatus::Cancelled, $request->fresh()->status);
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
