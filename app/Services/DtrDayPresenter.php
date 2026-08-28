<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\WorkSchedule;
use App\Support\DtrDayRow;
use App\Support\ManilaTime;
use Carbon\Carbon;

class DtrDayPresenter
{
    public function present(Attendance $row, WorkSchedule $schedule): DtrDayRow
    {
        $date = optional($row->attendance_date)->toDateString() ?: ManilaTime::todayDate();
        $day = ManilaTime::parse($date);
        $isFuture = $day->gt(ManilaTime::today());
        $status = $row->status instanceof AttendanceStatus
            ? $row->status
            : (AttendanceStatus::tryFrom((string) $row->status) ?? AttendanceStatus::Absent);

        $amIn = $amOut = $pmIn = $pmOut = null;
        $totalMinutes = 0;

        if ($this->hasStoredPunches($row)) {
            $amIn = $row->am_time_in;
            $amOut = $row->am_time_out;
            $pmIn = $row->pm_time_in;
            $pmOut = $row->pm_time_out;
            $totalMinutes = $this->totalMinutesFromPunches($amIn, $amOut, $pmIn, $pmOut);
        } elseif ($row->time_in || $row->time_out) {
            [$amIn, $amOut, $pmIn, $pmOut, $totalMinutes] = $this->splitPunches($date, $row->time_in, $row->time_out, $schedule);
        }

        $incomplete = $this->isIncomplete($row, $isFuture);
        $overtimeMinutes = max(0, (int) $row->overtime_minutes);

        return new DtrDayRow(
            date: $date,
            dateLabel: $day->format('m/d/Y'),
            dayName: $day->format('l'),
            amIn: $this->formatPunch($amIn),
            amOut: $this->formatPunch($amOut),
            pmIn: $this->formatPunch($pmIn),
            pmOut: $this->formatPunch($pmOut),
            overtime: $this->formatHours($overtimeMinutes) ?? $this->formatPunch($row->overtime_in),
            totalHours: $this->formatHours($totalMinutes),
            overtimeMinutes: $overtimeMinutes,
            totalMinutes: $totalMinutes,
            status: $status,
            incomplete: $incomplete,
            isFuture: $isFuture,
            source: $row->exists ? $row : null,
        );
    }

    /**
     * Legacy projection for backfill and old records without stored punches.
     *
     * @return array{0:?Carbon,1:?Carbon,2:?Carbon,3:?Carbon,4:int}
     */
    public function splitPunches(string $date, ?Carbon $timeIn, ?Carbon $timeOut, WorkSchedule $schedule): array
    {
        $amEnd = ManilaTime::combineDateAndTime($date, $this->timeString($schedule->break_start, '12:00:00'));
        $pmStart = ManilaTime::combineDateAndTime($date, $this->timeString($schedule->break_end, '13:00:00'));

        if ($timeIn && ! $timeOut) {
            if ($timeIn->lt($pmStart)) {
                return [$timeIn, null, null, null, 0];
            }

            return [null, null, $timeIn, null, 0];
        }

        if (! $timeIn && $timeOut) {
            if ($timeOut->lte($amEnd)) {
                return [null, $timeOut, null, null, 0];
            }

            return [null, null, null, $timeOut, 0];
        }

        if (! $timeIn || ! $timeOut || $timeOut->lte($timeIn)) {
            return [null, null, null, null, 0];
        }

        if ($timeOut->lte($amEnd) || ($timeIn->lt($amEnd) && $timeOut->lte($pmStart))) {
            return [$timeIn, $timeOut, null, null, $this->minutesBetween($timeIn, $timeOut)];
        }

        if ($timeIn->gte($pmStart) || $timeIn->gte($amEnd)) {
            return [null, null, $timeIn, $timeOut, $this->minutesBetween($timeIn, $timeOut)];
        }

        if ($timeIn->lt($amEnd) && $timeOut->gt($pmStart)) {
            $amMinutes = $this->minutesBetween($timeIn, $amEnd);
            $pmMinutes = $this->minutesBetween($pmStart, $timeOut);

            return [$timeIn, $amEnd, $pmStart, $timeOut, $amMinutes + $pmMinutes];
        }

        return [$timeIn, $timeOut, null, null, $this->minutesBetween($timeIn, $timeOut)];
    }

    public function formatHours(int $minutes): ?string
    {
        if ($minutes <= 0) {
            return null;
        }

        if ($minutes % 60 === 0) {
            return (string) intdiv($minutes, 60);
        }

        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($mins === 30) {
            return $hours > 0 ? $hours.'.5' : '0.5';
        }

        return sprintf('%d:%02d', $hours, $mins);
    }

    private function hasStoredPunches(Attendance $row): bool
    {
        return $row->am_time_in || $row->am_time_out || $row->pm_time_in || $row->pm_time_out || $row->overtime_in;
    }

    private function isIncomplete(Attendance $row, bool $isFuture): bool
    {
        if ($isFuture) {
            return false;
        }

        if ($this->hasStoredPunches($row)) {
            return (bool) ($row->am_time_in || $row->pm_time_in) && ! $row->pm_time_out;
        }

        return (bool) $row->time_in && ! $row->time_out;
    }

    private function totalMinutesFromPunches(?Carbon $amIn, ?Carbon $amOut, ?Carbon $pmIn, ?Carbon $pmOut): int
    {
        $am = ($amIn && $amOut && $amOut->gt($amIn)) ? $this->minutesBetween($amIn, $amOut) : 0;
        $pm = ($pmIn && $pmOut && $pmOut->gt($pmIn)) ? $this->minutesBetween($pmIn, $pmOut) : 0;

        return max(0, $am + $pm);
    }

    private function formatPunch(?Carbon $value): ?string
    {
        if (! $value) {
            return null;
        }

        return $value->timezone(ManilaTime::TIMEZONE)->format('g:i A');
    }

    private function minutesBetween(Carbon $from, Carbon $to): int
    {
        if ($to->lte($from)) {
            return 0;
        }

        return (int) floor(($to->getTimestamp() - $from->getTimestamp()) / 60);
    }

    private function timeString(mixed $value, string $fallback): string
    {
        if ($value instanceof Carbon) {
            return $value->format('H:i:s');
        }

        $text = trim((string) $value);

        return $text !== '' ? $text : $fallback;
    }
}
