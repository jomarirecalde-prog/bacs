<?php

namespace App\Enums;

enum ManualSalaryMonthlySubtype: string
{
    case FullMonth = 'full_month';
    case PartialMonth = 'partial_month';
    case Summary = 'summary';

    public function label(): string
    {
        return match ($this) {
            self::FullMonth => 'Full-month computation',
            self::PartialMonth => 'Partial-month computation',
            self::Summary => 'Monthly summary (non-payable)',
        };
    }

    public function isPayable(): bool
    {
        return $this !== self::Summary;
    }
}
