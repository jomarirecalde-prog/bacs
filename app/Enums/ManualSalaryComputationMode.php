<?php

namespace App\Enums;

enum ManualSalaryComputationMode: string
{
    case Period = 'period';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Period => 'Period-based (payroll cutoff)',
            self::Monthly => 'Monthly salary computation',
        };
    }
}
