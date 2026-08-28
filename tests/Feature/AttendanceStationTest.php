<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\BindingStatus;
use App\Enums\EmploymentStatus;
use App\Enums\StationDeviceStatus;
use App\Enums\StationStatus;
use App\Models\Attendance;
use App\Models\AttendanceStation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\StationDeviceBinding;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\EmployeeQrService;
use App\Services\StationBindingService;
use App\Support\ManilaTime;
use App\Support\SecureHash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStationTest extends TestCase
{
    use RefreshDatabase;

    private WorkSchedule $schedule;

    private Department $department;

    private EmployeeQrService $qr;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qr = app(EmployeeQrService::class);

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

    public function test_station_login_page_is_dedicated(): void
    {
        $this->get(route('station.login'))
            ->assertOk()
            ->assertSee('ATTENDANCE STATION')
            ->assertSee('Login to Station')
            ->assertDontSee('Welcome back');
    }

    public function test_admin_can_create_station(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.stations.store'), [
                'station_name' => 'Main Office Attendance Station',
                'station_code' => 'BACS-STATION-001',
                'password' => 'station-pass',
                'password_confirmation' => 'station-pass',
                'location' => 'Main Office Lobby',
                'description' => 'Lobby kiosk',
                'status' => StationStatus::Active->value,
                'idle_timeout_minutes' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance_stations', [
            'station_code' => 'BACS-STATION-001',
            'device_status' => StationDeviceStatus::Unbound->value,
        ]);

        $station = AttendanceStation::query()->first();
        $this->assertTrue(password_verify('station-pass', $station->password));
        $this->assertNotSame('station-pass', $station->password);
    }

    public function test_employee_cannot_manage_stations(): void
    {
        $employee = $this->makeEmployee('staff');
        $station = AttendanceStation::factory()->create();

        $this->actingAs($employee->user)
            ->get(route('admin.stations.index'))
            ->assertForbidden();

        $this->actingAs($employee->user)
            ->post(route('admin.stations.store'), [
                'station_name' => 'Rogue',
                'station_code' => 'BACS-STATION-999',
                'password' => 'station-pass',
                'password_confirmation' => 'station-pass',
                'location' => 'Somewhere',
                'status' => StationStatus::Active->value,
                'idle_timeout_minutes' => 0,
            ])
            ->assertForbidden();

        $this->actingAs($employee->user)
            ->post(route('admin.stations.unbind', $station), ['confirm' => 1])
            ->assertForbidden();
    }

    public function test_first_login_binds_device_and_second_device_is_rejected(): void
    {
        $station = AttendanceStation::factory()->create([
            'station_code' => 'BACS-STATION-001',
            'password' => 'station-pass',
        ]);

        $response = $this->post(route('station.login.store'), [
            'station_id' => 'BACS-STATION-001',
            'password' => 'station-pass',
        ]);
        $response->assertRedirect(route('station.dashboard'));
        $this->keepStationCookie($response);
        $this->actingAs($station->fresh(), 'station');

        $this->assertDatabaseHas('station_device_bindings', [
            'attendance_station_id' => $station->id,
            'status' => BindingStatus::Active->value,
        ]);
        $this->assertSame(StationDeviceStatus::Bound, $station->fresh()->device_status);

        $this->get(route('station.dashboard'))->assertOk()->assertSee('SCAN YOUR EMPLOYEE QR CODE');
    }

    public function test_bound_station_rejects_login_without_device_cookie(): void
    {
        $station = $this->makeBoundStation();

        $this->post(route('station.login.store'), [
            'station_id' => $station->station_code,
            'password' => 'station-pass',
        ])->assertRedirect()
            ->assertSessionHas('device_conflict')
            ->assertSessionHasErrors('device');

        $this->assertSame(StationDeviceStatus::Bound, $station->fresh()->device_status);
    }

    public function test_same_device_can_login_after_logout_without_unbinding(): void
    {
        $station = AttendanceStation::factory()->create([
            'station_code' => 'BACS-STATION-010',
            'password' => 'station-pass',
        ]);

        $response = $this->post(route('station.login.store'), [
            'station_id' => 'BACS-STATION-010',
            'password' => 'station-pass',
        ]);
        $response->assertRedirect(route('station.dashboard'));
        $this->keepStationCookie($response);

        $this->post(route('station.logout'))->assertRedirect(route('station.login'));

        $this->assertSame(StationDeviceStatus::Bound, $station->fresh()->device_status);
        $this->assertDatabaseHas('station_device_bindings', [
            'attendance_station_id' => $station->id,
            'status' => BindingStatus::Active->value,
        ]);

        $this->post(route('station.login.store'), [
            'station_id' => 'BACS-STATION-010',
            'password' => 'station-pass',
        ])->assertRedirect(route('station.dashboard'));
    }

    public function test_admin_device_reset_allows_new_device_to_bind(): void
    {
        $admin = User::factory()->admin()->create();
        $station = $this->makeBoundStation();

        $this->actingAs($admin)
            ->post(route('admin.stations.unbind', $station), ['confirm' => 1])
            ->assertRedirect();

        $this->assertSame(StationDeviceStatus::Unbound, $station->fresh()->device_status);

        $this->post(route('station.login.store'), [
            'station_id' => $station->station_code,
            'password' => 'station-pass',
        ])->assertRedirect(route('station.dashboard'));

        $this->assertSame(StationDeviceStatus::Bound, $station->fresh()->device_status);
        $this->assertSame(1, $station->bindings()->active()->count());
    }

    public function test_station_scan_records_full_dtr_sequence(): void
    {
        $employee = $this->makeEmployee('juan');
        $token = $this->qr->issue($employee);
        $station = $this->loginNewStation();
        $date = ManilaTime::todayDate();

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'AM_TIME_IN')
            ->assertJsonPath('next_action', 'AM_TIME_OUT')
            ->assertJsonPath('employee.name', $employee->fullName());

        $this->travelTo(ManilaTime::combineDateAndTime($date, '12:00'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'AM_TIME_OUT');

        $this->travelTo(ManilaTime::combineDateAndTime($date, '13:00'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'PM_TIME_IN');

        $this->travelTo(ManilaTime::combineDateAndTime($date, '17:00'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'PM_TIME_OUT');

        $record = Attendance::query()->first();
        $this->assertNotNull($record->am_time_in);
        $this->assertNotNull($record->am_time_out);
        $this->assertNotNull($record->pm_time_in);
        $this->assertNotNull($record->pm_time_out);
        $this->assertSame($station->id, $record->punch_stations['am_time_in']['station_id'] ?? null);

        $this->travelTo(ManilaTime::combineDateAndTime($date, '18:00'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'OVERTIME');

        $this->travelTo(ManilaTime::combineDateAndTime($date, '18:30'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'ATTENDANCE_COMPLETED')
            ->assertJsonPath('ok', false);
    }

    public function test_immediate_second_scan_is_duplicate_protection(): void
    {
        $employee = $this->makeEmployee('ana');
        $token = $this->qr->issue($employee);
        $this->loginNewStation();
        $date = ManilaTime::todayDate();

        $this->travelTo(ManilaTime::combineDateAndTime($date, '08:00'));
        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertJsonPath('code', 'AM_TIME_IN');

        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertJsonPath('code', 'DUPLICATE_SCAN');

        $this->assertNull(Attendance::query()->first()->am_time_out);
    }

    public function test_invalid_and_inactive_qr_scans_are_rejected(): void
    {
        $this->loginNewStation();

        $this->postJson(route('station.scan'), ['token' => 'not-a-bacs-token'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_QR');

        $employee = $this->makeEmployee('oldqr');
        $old = $this->qr->issue($employee);
        $this->qr->regenerate($employee);

        $this->postJson(route('station.scan'), ['token' => $old->plainToken()])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_QR');

        $inactive = $this->makeEmployee('inactive');
        $inactive->user->update(['status' => AccountStatus::Inactive]);
        $qr = $this->qr->issue($inactive);

        $this->postJson(route('station.scan'), ['token' => $qr->plainToken()])
            ->assertStatus(422)
            ->assertJsonPath('code', 'ACCOUNT_INACTIVE');
    }

    public function test_locked_station_cannot_record_attendance(): void
    {
        $employee = $this->makeEmployee('locked');
        $token = $this->qr->issue($employee);
        $station = $this->loginNewStation();
        $station->update(['status' => StationStatus::Locked]);

        $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertStatus(403)
            ->assertJsonPath('code', 'STATION_LOCKED');

        $this->assertDatabaseCount('attendance', 0);
    }

    public function test_employee_can_view_own_qr_but_not_admin_qr_tools(): void
    {
        $a = $this->makeEmployee('alpha');
        $b = $this->makeEmployee('beta');
        $this->qr->issue($a);

        $this->actingAs($a->user)
            ->get(route('employee.qr'))
            ->assertOk()
            ->assertSee($a->fullName())
            ->assertDontSee($b->fullName());

        $this->actingAs($a->user)
            ->get(route('admin.employees.qr', $b))
            ->assertForbidden();
    }

    public function test_admin_can_regenerate_employee_qr(): void
    {
        $admin = User::factory()->admin()->create();
        $employee = $this->makeEmployee('worker');
        $old = $this->qr->issue($employee);

        $this->actingAs($admin)
            ->post(route('admin.employees.qr.regenerate', $employee))
            ->assertRedirect();

        $this->assertSame('revoked', $old->fresh()->status->value);
        $this->assertNotNull($employee->activeQrToken());
        $this->assertNotSame($old->id, $employee->activeQrToken()->id);
    }

    public function test_heartbeat_updates_last_seen(): void
    {
        $station = $this->loginNewStation();
        $this->assertNotNull($station->fresh()->last_seen_at);

        $this->postJson(route('station.heartbeat'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('locked', false);
    }

    public function test_wrong_password_is_rate_limited_and_logged(): void
    {
        $station = AttendanceStation::factory()->create([
            'station_code' => 'BACS-STATION-077',
            'password' => 'station-pass',
        ]);

        $this->post(route('station.login.store'), [
            'station_id' => 'BACS-STATION-077',
            'password' => 'wrong',
        ])->assertSessionHasErrors('station_id');

        $this->assertSame(1, $station->fresh()->failed_login_attempts);
        $this->assertDatabaseHas('station_activity_logs', [
            'attendance_station_id' => $station->id,
            'action' => 'login',
            'result' => 'failure',
        ]);
    }

    private function loginNewStation(array $attrs = []): AttendanceStation
    {
        $station = AttendanceStation::factory()->create(array_merge([
            'password' => 'station-pass',
        ], $attrs));

        $response = $this->post(route('station.login.store'), [
            'station_id' => $station->station_code,
            'password' => 'station-pass',
        ]);

        $response->assertRedirect(route('station.dashboard'));
        $this->keepStationCookie($response);
        $this->actingAs($station->fresh(), 'station');

        return $station->fresh();
    }

    private function keepStationCookie($response): void
    {
        $cookie = $response->getCookie(StationBindingService::COOKIE, decrypt: true);
        if ($cookie) {
            $this->withCookie(StationBindingService::COOKIE, $cookie->getValue());
        }

        $this->withCredentials();
    }

    private function makeBoundStation(): AttendanceStation
    {
        $station = AttendanceStation::factory()->create([
            'password' => 'station-pass',
            'device_status' => StationDeviceStatus::Bound,
            'binding_nonce' => 'nonce-one',
        ]);

        StationDeviceBinding::query()->create([
            'attendance_station_id' => $station->id,
            'device_identifier_hash' => SecureHash::make('device-secret'),
            'binding_token_hash' => SecureHash::make('binding-secret'),
            'bound_at' => ManilaTime::now(),
            'status' => BindingStatus::Active,
        ]);

        return $station;
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
            'position' => 'Field Staff',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $this->schedule->id,
        ]);
    }
}
