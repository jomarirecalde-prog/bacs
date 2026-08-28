<?php

namespace App\Enums;

enum AttendancePunchType: string
{
    case AmTimeIn = 'am_time_in';
    case AmTimeOut = 'am_time_out';
    case PmTimeIn = 'pm_time_in';
    case PmTimeOut = 'pm_time_out';
    case Overtime = 'overtime';

    /** @return list<self> */
    public static function regularSequence(): array
    {
        return [
            self::AmTimeIn,
            self::AmTimeOut,
            self::PmTimeIn,
            self::PmTimeOut,
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::AmTimeIn => 'AM Time In',
            self::AmTimeOut => 'AM Time Out',
            self::PmTimeIn => 'PM Time In',
            self::PmTimeOut => 'PM Time Out',
            self::Overtime => 'Overtime',
        };
    }

    public function scanCode(): string
    {
        return strtoupper($this->value);
    }

    public function column(): string
    {
        return $this === self::Overtime ? 'overtime_in' : $this->value;
    }

    public function next(): ?self
    {
        return match ($this) {
            self::AmTimeIn => self::AmTimeOut,
            self::AmTimeOut => self::PmTimeIn,
            self::PmTimeIn => self::PmTimeOut,
            self::PmTimeOut => self::Overtime,
            self::Overtime => null,
        };
    }

    public function isRegular(): bool
    {
        return $this !== self::Overtime;
    }
}
