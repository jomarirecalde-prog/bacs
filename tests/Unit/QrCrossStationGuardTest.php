<?php

namespace Tests\Unit;

use App\Enums\EmploymentStatus;
use App\Models\AttendanceStation;
use App\Models\Department;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\QrCrossStationGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class QrCrossStationGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_scan_at_second_station_within_window(): void
    {
        config(['attendance.qr_cross_station_window_seconds' => 120]);

        [$employee, $stationA, $stationB] = $this->fixtures();

        $guard = app(QrCrossStationGuard::class);
        $guard->rememberSuccessfulScan($employee, $stationA);

        $block = $guard->blockReason($employee, $stationB);

        $this->assertNotNull($block);
        $this->assertSame('CROSS_STATION_SCAN', $block['code']);
    }

    public function test_allows_rescan_at_same_station(): void
    {
        config(['attendance.qr_cross_station_window_seconds' => 120]);

        [$employee, $station] = $this->fixtures();

        $guard = app(QrCrossStationGuard::class);
        $guard->rememberSuccessfulScan($employee, $station);

        $this->assertNull($guard->blockReason($employee, $station));
    }

    /** @return array{0: Employee, 1: AttendanceStation, 2?: AttendanceStation} */
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
            'status' => 'active',
        ]);
        Department::query()->create(['name' => 'Ops', 'status' => 'active']);
        $user = User::factory()->create();
        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'QR-GUARD-1',
            'first_name' => 'A',
            'last_name' => 'B',
            'email' => $user->email,
            'department_id' => 1,
            'employment_status' => EmploymentStatus::Regular,
        ]);
        $stationA = AttendanceStation::factory()->create();
        $stationB = AttendanceStation::factory()->create();

        return [$employee, $stationA, $stationB];
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
