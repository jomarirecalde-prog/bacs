<?php

namespace App\Enums;

enum SalaryType: string
{
    case Monthly = 'monthly';
    case SemiMonthly = 'semi_monthly';
    case Daily = 'daily';
    case Hourly = 'hourly';
    case FixedPeriod = 'fixed_period';

    public function label(): string
    {
        return match ($this) {
            self::Monthly => 'Monthly',
            self::SemiMonthly => 'Semi-Monthly',
            self::Daily => 'Daily',
            self::Hourly => 'Hourly',
            self::FixedPeriod => 'Fixed Period',
        };
    }
}
