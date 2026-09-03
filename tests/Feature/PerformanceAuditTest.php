<?php

namespace Tests\Feature;

use App\Enums\EmploymentStatus;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Models\AttendanceStation;
use App\Services\EmployeeQrService;
use App\Services\StationBindingService;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PerformanceAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_does_not_query_per_employee(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'perf-admin']);
        $schedule = $this->schedule();
        $department = $this->department();

        for ($i = 0; $i < 12; $i++) {
            $this->employee("staff{$i}", $department, $schedule);
        }

        Leave::query()->create([
            'employee_id' => Employee::query()->first()->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->toDateString(),
            'type' => 'vacation',
            'status' => 'approved',
        ]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk();

        $queries = collect(DB::getQueryLog());
        $leaveQueries = $queries->filter(fn (array $q) => str_contains(strtolower($q['query']), 'from "leaves"')
            || str_contains(strtolower($q['query']), 'from `leaves`'));

        $this->assertLessThan(
            55,
            $queries->count(),
            'Admin dashboard issued '.$queries->count().' queries. Expected a bounded set after N+1 fixes.'
        );
        $this->assertLessThanOrEqual(
            1,
            $leaveQueries->count(),
            'Leave lookups should be batched, not one query per employee.'
        );
    }

    public function test_partial_navigation_skips_notification_history(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'partial-admin']);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($admin)
            ->withHeaders(['X-BACS-Partial' => '1'])
            ->get(route('admin.dtr.index'))
            ->assertOk()
            ->assertSee('bacs-partial', false);

        $queries = collect(DB::getQueryLog());
        $notificationSelects = $queries->filter(function (array $q) {
            $sql = strtolower($q['query']);

            return str_contains($sql, 'app_notifications') && str_contains($sql, 'select');
        });

        $this->assertSame(
            0,
            $notificationSelects->count(),
            'Partial navigations must not reload the notification bell.'
        );
    }

    public function test_employee_attendance_defaults_to_current_month(): void
    {
        $schedule = $this->schedule();
        $department = $this->department();
        $employee = $this->employee('monthstaff', $department, $schedule);

        $this->actingAs($employee->user)
            ->get(route('employee.attendance'))
            ->assertOk();
    }

    public function test_notifications_feed_can_return_count_only(): void
    {
        $user = User::factory()->admin()->create(['username' => 'bell']);

        $response = $this->actingAs($user)
            ->getJson(route('notifications.index', ['items' => 0]));

        $response->assertOk()->assertJsonStructure(['unread']);
        $this->assertArrayNotHasKey('items', $response->json());
    }

    public function test_station_scan_critical_path_uses_bounded_queries(): void
    {
        $schedule = $this->schedule();
        $department = $this->department();
        $employee = $this->employee('scanperf', $department, $schedule);
        $token = app(EmployeeQrService::class)->issue($employee);
        $this->loginStation();

        $this->travelTo(ManilaTime::combineDateAndTime(ManilaTime::todayDate(), '08:00'));

        config(['app.debug' => true]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->postJson(route('station.scan'), ['token' => $token->plainToken()])
            ->assertOk()
            ->assertJsonPath('code', 'AM_TIME_IN');

        $queryCount = count(DB::getQueryLog());

        $this->assertLessThan(
            32,
            $queryCount,
            'Station scan issued '.$queryCount.' database queries (including deferred side effects).'
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'station_am_time_in',
            'module' => 'Attendance',
        ]);
    }

    public function test_admin_dashboard_live_skips_roster_payload(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'live-admin']);
        $schedule = $this->schedule();
        $department = $this->department();
        $this->employee('livestaff', $department, $schedule);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.dashboard.live'))
            ->assertOk()
            ->assertJsonStructure(['summary', 'departments', 'server_time']);

        $this->assertArrayNotHasKey('rows', $response->json());

        $queries = collect(DB::getQueryLog());
        $this->assertLessThan(
            40,
            $queries->count(),
            'Live poll issued '.$queries->count().' queries; roster rebuild should be gone.'
        );
    }

    public function test_approver_search_requires_minimum_length(): void
    {
        $admin = User::factory()->admin()->create(['username' => 'search-admin']);

        $this->actingAs($admin)
            ->getJson(route('admin.leave.workflow.employees.search', ['q' => 'J']))
            ->assertOk()
            ->assertJson(['results' => []]);
    }

    private function loginStation(): AttendanceStation
    {
        $station = AttendanceStation::factory()->create([
            'password' => 'station-pass',
        ]);

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

        return $station;
    }

    private function schedule(): WorkSchedule
    {
        return WorkSchedule::query()->create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => 'active',
        ]);
    }

    private function department(): Department
    {
        return Department::query()->create([
            'name' => 'Operations',
            'status' => 'active',
        ]);
    }

    private function employee(string $username, Department $department, WorkSchedule $schedule): Employee
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
            'department_id' => $department->id,
            'position' => 'Staff',
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
        ]);
    }
}
