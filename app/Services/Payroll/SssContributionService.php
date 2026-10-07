<?php

namespace App\Services\Payroll;

use App\Models\PayrollSssBracket;
use App\Support\Money;

class SssContributionService
{
    public function monthlyEmployeeShare(float $monthlyCompensation, ?string $asOfDate = null): float
    {
        if ($monthlyCompensation <= 0) {
            return 0.0;
        }

        $asOf = $asOfDate ?? now()->toDateString();

        $latestEffective = PayrollSssBracket::query()
            ->where('effective_from', '<=', $asOf)
            ->max('effective_from');

        if (! $latestEffective) {
            return 0.0;
        }

        $brackets = PayrollSssBracket::query()
            ->whereDate('effective_from', $latestEffective)
            ->orderBy('sort_order')
            ->get();

        foreach ($brackets as $bracket) {
            $min = (float) $bracket->compensation_min;
            $max = $bracket->compensation_max !== null ? (float) $bracket->compensation_max : null;

            if ($monthlyCompensation < $min) {
                continue;
            }

            if ($max === null || $monthlyCompensation <= $max) {
                return Money::toFloat(Money::round((float) $bracket->employee_share_monthly));
            }
        }

        $last = $brackets->last();

        return $last
            ? Money::toFloat(Money::round((float) $last->employee_share_monthly))
            : 0.0;
    }

    public function semiMonthlyEmployeeShare(float $monthlyCompensation, ?string $asOfDate = null): float
    {
        $monthly = $this->monthlyEmployeeShare($monthlyCompensation, $asOfDate);

        return Money::toFloat(Money::round($monthly / 2));
    }
}
