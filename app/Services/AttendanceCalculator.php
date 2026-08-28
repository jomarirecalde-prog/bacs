<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Leave;
use App\Models\WorkSchedule;
use App\Support\ManilaTime;
use Carbon\Carbon;

class AttendanceCalculator
{
    /**
     * Optional so existing callers (and unit tests) can still do
     * `new AttendanceCalculator` without wiring the container.
     */
    public function __construct(private ?HolidayResolver $holidays = null) {}

    private function holidays(): HolidayResolver
    {
        return $this->holidays ??= app(HolidayResolver::class);
    }

    public function calculate(
        string $date,
        ?Carbon $timeIn,
        ?Carbon $timeOut,
        WorkSchedule $schedule,
        ?int $employeeId = null,
        ?AttendanceStatus $forcedStatus = null
    ): array {
        if ($forcedStatus) {
            return $this->emptyResult($forcedStatus);
        }

        if ($employeeId && Leave::approvedOn($employeeId, $date)) {
            return $this->emptyResult(AttendanceStatus::OnLeave);
        }

        $holiday = $this->holidays()->forDate($date, $employeeId);
        $day = ManilaTime::parse($date);
        $isWorkDay = $schedule->isWorkDay((int) $day->isoWeekday());

        if (! $timeIn) {
            if ($holiday) {
                return $this->emptyResult(AttendanceStatus::Holiday);
            }

            if (! $isWorkDay) {
                return $this->emptyResult(AttendanceStatus::RestDay);
            }

            return $this->emptyResult(AttendanceStatus::Absent);
        }

        $start = ManilaTime::combineDateAndTime($date, (string) $schedule->start_time);
        $end = ManilaTime::combineDateAndTime($date, (string) $schedule->end_time);
        $allowedIn = $start->copy()->addMinutes((int) $schedule->grace_period_minutes);

        $lateMinutes = 0;
        if ($timeIn->gt($allowedIn)) {
            $lateMinutes = (int) floor(($timeIn->getTimestamp() - $allowedIn->getTimestamp()) / 60);
        }

        if (! $timeOut) {
            return [
                'total_minutes' => 0,
                'late_minutes' => max(0, $lateMinutes),
                'undertime_minutes' => 0,
                'overtime_minutes' => 0,
                'status' => AttendanceStatus::Incomplete,
            ];
        }

        if ($timeOut->lte($timeIn)) {
            throw new \InvalidArgumentException('Time Out must be later than Time In.');
        }

        $workedMinutes = (int) floor(($timeOut->getTimestamp() - $timeIn->getTimestamp()) / 60);
        $breakMinutes = $this->breakOverlapMinutes($date, $timeIn, $timeOut, $schedule);
        $netMinutes = max(0, $workedMinutes - $breakMinutes);

        $required = (int) ($schedule->required_minutes ?: $this->defaultRequiredMinutes($schedule));

        $undertimeMinutes = 0;
        if ($timeOut->lt($end)) {
            $undertimeMinutes = (int) floor(($end->getTimestamp() - $timeOut->getTimestamp()) / 60);
        }

        $overtimeMinutes = 0;
        if ($timeOut->gt($end)) {
            $overtimeMinutes = (int) floor(($timeOut->getTimestamp() - $end->getTimestamp()) / 60);
        }

        if ($holiday || ! $isWorkDay) {
            $overtimeMinutes = $netMinutes;
            $undertimeMinutes = 0;
        }

        $status = $this->resolveStatus(
            $lateMinutes,
            $undertimeMinutes,
            $overtimeMinutes,
            $netMinutes,
            $required
        );

        return [
            'total_minutes' => $netMinutes,
            'late_minutes' => max(0, $lateMinutes),
            'undertime_minutes' => max(0, $undertimeMinutes),
            'overtime_minutes' => max(0, $overtimeMinutes),
            'status' => $status,
        ];
    }

    private function resolveStatus(
        int $lateMinutes,
        int $undertimeMinutes,
        int $overtimeMinutes,
        int $netMinutes,
        int $required
    ): AttendanceStatus {
        if ($required > 0 && $netMinutes > 0 && $netMinutes < (int) floor($required / 2)) {
            return AttendanceStatus::HalfDay;
        }

        if ($lateMinutes > 0) {
            return AttendanceStatus::Late;
        }

        if ($undertimeMinutes > 0) {
            return AttendanceStatus::Undertime;
        }

        if ($overtimeMinutes > 0) {
            return AttendanceStatus::Overtime;
        }

        return AttendanceStatus::Present;
    }

    private function breakOverlapMinutes(string $date, Carbon $timeIn, Carbon $timeOut, WorkSchedule $schedule): int
    {
        if (! $schedule->break_start || ! $schedule->break_end) {
            return 0;
        }

        $breakStart = ManilaTime::combineDateAndTime($date, (string) $schedule->break_start);
        $breakEnd = ManilaTime::combineDateAndTime($date, (string) $schedule->break_end);

        $overlapStart = $timeIn->greaterThan($breakStart) ? $timeIn : $breakStart;
        $overlapEnd = $timeOut->lessThan($breakEnd) ? $timeOut : $breakEnd;

        if ($overlapEnd->lte($overlapStart)) {
            return 0;
        }

        return (int) floor(($overlapEnd->getTimestamp() - $overlapStart->getTimestamp()) / 60);
    }

    private function defaultRequiredMinutes(WorkSchedule $schedule): int
    {
        $start = Carbon::parse((string) $schedule->start_time);
        $end = Carbon::parse((string) $schedule->end_time);
        $span = (int) floor(($end->getTimestamp() - $start->getTimestamp()) / 60);

        $break = 0;
        if ($schedule->break_start && $schedule->break_end) {
            $break = (int) floor((Carbon::parse((string) $schedule->break_end)->getTimestamp() - Carbon::parse((string) $schedule->break_start)->getTimestamp()) / 60);
        }

        return max(0, $span - $break);
    }

    private function emptyResult(AttendanceStatus $status): array
    {
        return [
            'total_minutes' => 0,
            'late_minutes' => 0,
            'undertime_minutes' => 0,
            'overtime_minutes' => 0,
            'status' => $status,
        ];
    }
}
