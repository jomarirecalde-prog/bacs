<?php

namespace App\Enums;

enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
    case HalfDay = 'half_day';
    case OnLeave = 'on_leave';
    case RestDay = 'rest_day';
    case Holiday = 'holiday';
    case Incomplete = 'incomplete';
    case Undertime = 'undertime';
    case Overtime = 'overtime';

    public function label(): string
    {
        return match ($this) {
            self::Present => 'Present',
            self::Late => 'Late',
            self::Absent => 'Absent',
            self::HalfDay => 'Half Day',
            self::OnLeave => 'On Leave',
            self::RestDay => 'Rest Day',
            self::Holiday => 'Holiday',
            self::Incomplete => 'Incomplete',
            self::Undertime => 'Undertime',
            self::Overtime => 'Overtime',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Present => 'check-circle',
            self::Late => 'clock',
            self::Absent => 'x-circle',
            self::HalfDay => 'adjustments',
            self::OnLeave => 'calendar',
            self::RestDay => 'moon',
            self::Holiday => 'star',
            self::Incomplete => 'exclamation',
            self::Undertime => 'arrow-down',
            self::Overtime => 'arrow-up',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Present => 'green',
            self::Late => 'orange',
            self::Absent => 'red',
            self::HalfDay => 'purple',
            self::OnLeave => 'blue',
            self::RestDay => 'slate',
            self::Holiday => 'indigo',
            self::Incomplete => 'yellow',
            self::Undertime => 'amber',
            self::Overtime => 'teal',
        };
    }
}
