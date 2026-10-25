<?php

namespace App\Enums;

enum ManualSalaryPayableIntent: string
{
    case Standard = 'standard';
    case Supplemental = 'supplemental';
    case Adjustment = 'adjustment';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard payroll',
            self::Supplemental => 'Supplemental payroll',
            self::Adjustment => 'Adjustment / correction',
        };
    }
}
