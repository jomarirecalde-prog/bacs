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
}
