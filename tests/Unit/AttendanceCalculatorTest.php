<?php

namespace Tests\Unit;

use App\Enums\AttendanceStatus;
use App\Models\WorkSchedule;
use App\Services\AttendanceCalculator;
use App\Support\ManilaTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_late_minutes_use_grace_period(): void
    {
        $schedule = new WorkSchedule([
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
        ]);

        $date = '2026-08-17';
        $timeIn = ManilaTime::combineDateAndTime($date, '08:18');
        $timeOut = ManilaTime::combineDateAndTime($date, '17:00');

        $result = (new AttendanceCalculator)->calculate($date, $timeIn, $timeOut, $schedule);

        $this->assertSame(8, $result['late_minutes']);
        $this->assertSame(AttendanceStatus::Late, $result['status']);
    }
}
