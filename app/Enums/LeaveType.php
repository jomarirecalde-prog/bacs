<?php

namespace App\Enums;

enum LeaveType: string
{
    case Vacation = 'vacation';
    case Sick = 'sick';
    case Birthday = 'birthday';
    case Bereavement = 'bereavement';
    case Special = 'special';

    public function label(): string
    {
        return match ($this) {
            self::Vacation => 'Vacation Leave',
            self::Sick => 'Sick Leave',
            self::Birthday => 'Birthday Leave',
            self::Bereavement => 'Bereavement Leave',
            self::Special => 'Special Leave',
        };
    }

    public function defaultDays(): int
    {
        return match ($this) {
            self::Vacation => 5,
            self::Sick => 3,
            self::Birthday => 1,
            self::Bereavement => 2,
            self::Special => 0,
        };
    }

    public function countsCalendarDays(): bool
    {
        return $this === self::Special;
    }

    public function formColumn(): string
    {
        return match ($this) {
            self::Vacation, self::Sick, self::Bereavement => 'left',
            self::Birthday, self::Special => 'right',
        };
    }
}
