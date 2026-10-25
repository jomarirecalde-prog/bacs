<?php

namespace App\Enums;

enum PayrollWorkdayClassification: string
{
    case Ordinary = 'ordinary';
    case SpecialNonWorking = 'special_non_working';
    case RegularHoliday = 'regular_holiday';
    case DoubleSpecialNonWorking = 'double_special_non_working';
    case DoubleHoliday = 'double_holiday';

    public function label(): string
    {
        return match ($this) {
            self::Ordinary => 'Ordinary day',
            self::SpecialNonWorking => 'Special non-working day',
            self::RegularHoliday => 'Regular holiday',
            self::DoubleSpecialNonWorking => 'Double special non-working day',
            self::DoubleHoliday => 'Double holiday',
        };
    }
}
