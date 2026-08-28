<?php

namespace Tests\Unit;

use App\Enums\AccountStatus;
use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\WorkSchedule;
use App\Services\DtrDayPresenter;
use App\Support\DtrPeriod;
use App\Support\ManilaTime;
use Tests\TestCase;

class DtrDayPresenterTest extends TestCase
{
    private WorkSchedule $schedule;

    private DtrDayPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->schedule = new WorkSchedule([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'status' => AccountStatus::Active,
        ]);

        $this->presenter = new DtrDayPresenter;
    }

    public function test_full_day_uses_scheduled_break_for_am_out_and_pm_in(): void
    {
        $date = '2026-08-11';
        $row = $this->attendance($date, '08:00', '18:00', 60);

        $day = $this->presenter->present($row, $this->schedule);

        $this->assertSame('08/11/2026', $day->dateLabel);
        $this->assertSame('Tuesday', $day->dayName);
        $this->assertSame('8:00 AM', $day->amIn);
        $this->assertSame('12:00 PM', $day->amOut);
        $this->assertSame('1:00 PM', $day->pmIn);
        $this->assertSame('6:00 PM', $day->pmOut);
        $this->assertSame('9', $day->totalHours);
        $this->assertSame('1', $day->overtime);
    }

    public function test_morning_only_does_not_fill_pm_columns(): void
    {
        $row = $this->attendance('2026-08-15', '09:00', '12:00');

        $day = $this->presenter->present($row, $this->schedule);

        $this->assertSame('9:00 AM', $day->amIn);
        $this->assertSame('12:00 PM', $day->amOut);
        $this->assertNull($day->pmIn);
        $this->assertNull($day->pmOut);
        $this->assertSame('3', $day->totalHours);
        $this->assertNull($day->overtime);
        $this->assertSame('—', $day->cell($day->pmIn));
    }

    public function test_missing_time_out_is_incomplete_and_not_copied(): void
    {
        $row = $this->attendance('2026-08-17', '08:05', null);
        $row->status = AttendanceStatus::Incomplete;

        $day = $this->presenter->present($row, $this->schedule);

        $this->assertSame('8:05 AM', $day->amIn);
        $this->assertNull($day->amOut);
        $this->assertNull($day->pmIn);
        $this->assertNull($day->pmOut);
        $this->assertNull($day->totalHours);
        $this->assertTrue($day->incomplete);
    }

    public function test_leave_and_rest_days_do_not_fabricate_times(): void
    {
        $leave = new Attendance([
            'attendance_date' => '2026-08-18',
            'status' => AttendanceStatus::OnLeave,
        ]);

        $day = $this->presenter->present($leave, $this->schedule);

        $this->assertNull($day->amIn);
        $this->assertNull($day->totalHours);
        $this->assertSame(AttendanceStatus::OnLeave, $day->status);
    }

    public function test_cutoff_containing_mid_month_is_the_11_to_25_period(): void
    {
        $period = DtrPeriod::cutoffContaining(ManilaTime::parse('2026-08-20'));

        $this->assertSame('2026-08-11', $period->start);
        $this->assertSame('2026-08-25', $period->end);
        $this->assertSame('cutoff', $period->type);
    }

    public function test_cutoff_spanning_months_uses_the_26_to_10_window(): void
    {
        $period = DtrPeriod::cutoffContaining(ManilaTime::parse('2026-08-28'));

        $this->assertSame('2026-08-26', $period->start);
        $this->assertSame('2026-09-10', $period->end);
    }

    private function attendance(string $date, ?string $in, ?string $out, int $overtime = 0): Attendance
    {
        return new Attendance([
            'attendance_date' => $date,
            'time_in' => $in ? ManilaTime::combineDateAndTime($date, $in) : null,
            'time_out' => $out ? ManilaTime::combineDateAndTime($date, $out) : null,
            'overtime_minutes' => $overtime,
            'status' => AttendanceStatus::Present,
        ]);
    }
}
