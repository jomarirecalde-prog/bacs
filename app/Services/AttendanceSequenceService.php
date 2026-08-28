<?php

namespace App\Services;

use App\Enums\AttendancePunchType;
use App\Models\Attendance;
use App\Models\WorkSchedule;
use App\Support\ManilaTime;
use Carbon\Carbon;

class AttendanceSequenceService
{
    public const DUPLICATE_SCAN_SECONDS = 10;

    /** Minutes before/after a schedule anchor when a punch is considered valid. */
    private const WINDOW_AM_IN_BEFORE = 120;
    private const WINDOW_AM_IN_AFTER = 180;
    private const WINDOW_AM_OUT_BEFORE = 30;
    private const WINDOW_AM_OUT_AFTER = 60;
    private const WINDOW_PM_IN_BEFORE = 15;
    private const WINDOW_PM_IN_AFTER = 90;
    private const WINDOW_PM_OUT_BEFORE = 0;
    private const WINDOW_PM_OUT_AFTER = 120;
    private const WINDOW_OVERTIME_AFTER = 0;

    public function nextExpected(?Attendance $record): ?AttendancePunchType
    {
        foreach (AttendancePunchType::regularSequence() as $type) {
            if (! $this->hasPunch($record, $type)) {
                return $type;
            }
        }

        if ($this->allowsOvertime($record) && ! $this->hasPunch($record, AttendancePunchType::Overtime)) {
            return AttendancePunchType::Overtime;
        }

        return null;
    }

    public function allowsOvertime(?Attendance $record): bool
    {
        if (! $record) {
            return false;
        }

        foreach (AttendancePunchType::regularSequence() as $type) {
            if (! $this->hasPunch($record, $type)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{type: ?AttendancePunchType, code: ?string, message: ?string}
     */
    public function resolveScan(?Attendance $record, Carbon $now, WorkSchedule $schedule): array
    {
        $date = $record?->attendance_date?->toDateString() ?? $now->toDateString();
        $next = $this->nextExpected($record);

        if (! $next) {
            return [
                'type' => null,
                'code' => 'ATTENDANCE_COMPLETED',
                'message' => 'Your attendance for today is already complete.',
            ];
        }

        if ($this->isDuplicateScan($record, $next, $now)) {
            return [
                'type' => null,
                'code' => 'DUPLICATE_SCAN',
                'message' => $next->label().' has already been recorded.',
            ];
        }

        $lastPunch = $this->lastRecordedPunch($record);
        if ($lastPunch
            && $lastPunch['at']->gte($now->copy()->subSeconds(self::DUPLICATE_SCAN_SECONDS))
            && ! $this->isWithinWindow($now, $schedule, $next, $date)) {
            return [
                'type' => null,
                'code' => 'DUPLICATE_SCAN',
                'message' => $lastPunch['type']->label().' has already been recorded.',
            ];
        }

        if ($next === AttendancePunchType::Overtime) {
            if (! $this->isWithinWindow($now, $schedule, $next, $date)) {
                return [
                    'type' => null,
                    'code' => 'OVERTIME_NOT_ALLOWED',
                    'message' => 'Overtime is not allowed at this time.',
                ];
            }

            return ['type' => $next, 'code' => null, 'message' => null];
        }

        $missingEarlier = $this->firstMissingRegular($record);

        if ($missingEarlier && $missingEarlier !== $next) {
            $timeMatch = $this->matchingPunchForTime($record, $now, $schedule, $date);

            if ($timeMatch && $timeMatch !== $next && ! $this->hasPunch($record, $timeMatch)) {
                return [
                    'type' => null,
                    'code' => 'INVALID_SEQUENCE',
                    'message' => 'Missing '.$missingEarlier->label().'. Please contact your administrator for a correction.',
                ];
            }
        }

        if (! $this->isWithinWindow($now, $schedule, $next, $date)) {
            return [
                'type' => null,
                'code' => 'INVALID_SCAN_TIME',
                'message' => 'This scan is outside the allowed time for '.$next->label().'.',
            ];
        }

        return ['type' => $next, 'code' => null, 'message' => null];
    }

    public function isDuplicateScan(?Attendance $record, AttendancePunchType $type, Carbon $now): bool
    {
        if (! $record) {
            return false;
        }

        $existing = $this->punchAt($record, $type);
        if (! $existing) {
            return false;
        }

        return $existing->gte($now->copy()->subSeconds(self::DUPLICATE_SCAN_SECONDS));
    }

    public function hasPunch(?Attendance $record, AttendancePunchType $type): bool
    {
        return $this->punchAt($record, $type) !== null;
    }

    public function punchAt(?Attendance $record, AttendancePunchType $type): ?Carbon
    {
        if (! $record) {
            return null;
        }

        $value = $record->{$type->column()};

        return $value instanceof Carbon ? $value : ($value ? ManilaTime::parse($value) : null);
    }

    public function isRegularComplete(?Attendance $record): bool
    {
        foreach (AttendancePunchType::regularSequence() as $type) {
            if (! $this->hasPunch($record, $type)) {
                return false;
            }
        }

        return true;
    }

    /** @return array{type: AttendancePunchType, at: Carbon}|null */
    private function lastRecordedPunch(?Attendance $record): ?array
    {
        if (! $record) {
            return null;
        }

        $last = null;

        foreach (AttendancePunchType::cases() as $type) {
            $at = $this->punchAt($record, $type);
            if ($at && (! $last || $at->gt($last['at']))) {
                $last = ['type' => $type, 'at' => $at];
            }
        }

        return $last;
    }

    private function firstMissingRegular(?Attendance $record): ?AttendancePunchType
    {
        foreach (AttendancePunchType::regularSequence() as $type) {
            if (! $this->hasPunch($record, $type)) {
                return $type;
            }
        }

        return null;
    }

    private function matchingPunchForTime(?Attendance $record, Carbon $now, WorkSchedule $schedule, string $date): ?AttendancePunchType
    {
        foreach (AttendancePunchType::regularSequence() as $type) {
            if ($this->hasPunch($record, $type)) {
                continue;
            }

            if ($this->isWithinWindow($now, $schedule, $type, $date)) {
                return $type;
            }
        }

        return null;
    }

    private function isWithinWindow(Carbon $now, WorkSchedule $schedule, AttendancePunchType $type, string $date): bool
    {
        $start = ManilaTime::combineDateAndTime($date, (string) $schedule->start_time);
        $end = ManilaTime::combineDateAndTime($date, (string) $schedule->end_time);
        $breakStart = ManilaTime::combineDateAndTime($date, $this->timeString($schedule->break_start, '12:00:00'));
        $breakEnd = ManilaTime::combineDateAndTime($date, $this->timeString($schedule->break_end, '13:00:00'));

        [$from, $until] = match ($type) {
            AttendancePunchType::AmTimeIn => [
                $start->copy()->subMinutes(self::WINDOW_AM_IN_BEFORE),
                $breakStart->copy()->addMinutes(self::WINDOW_AM_IN_AFTER),
            ],
            AttendancePunchType::AmTimeOut => [
                $breakStart->copy()->subMinutes(self::WINDOW_AM_OUT_BEFORE),
                $breakEnd->copy()->addMinutes(self::WINDOW_AM_OUT_AFTER),
            ],
            AttendancePunchType::PmTimeIn => [
                $breakEnd->copy()->subMinutes(self::WINDOW_PM_IN_BEFORE),
                $breakEnd->copy()->addMinutes(self::WINDOW_PM_IN_AFTER),
            ],
            AttendancePunchType::PmTimeOut => [
                $breakEnd->copy()->subMinutes(self::WINDOW_PM_OUT_BEFORE),
                $end->copy()->addMinutes(self::WINDOW_PM_OUT_AFTER),
            ],
            AttendancePunchType::Overtime => [
                $end->copy()->addMinutes(self::WINDOW_OVERTIME_AFTER),
                $end->copy()->addHours(8),
            ],
        };

        return $now->gte($from) && $now->lte($until);
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
