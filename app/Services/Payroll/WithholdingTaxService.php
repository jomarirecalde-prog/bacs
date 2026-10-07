<?php

namespace App\Services\Payroll;

use App\Models\PayrollTaxBracket;
use App\Support\Money;

class WithholdingTaxService
{
    public function monthlyTax(float $monthlyTaxable, ?string $asOfDate = null): float
    {
        if ($monthlyTaxable <= 0) {
            return 0.0;
        }

        $asOf = $asOfDate ?? now()->toDateString();

        $latestEffective = PayrollTaxBracket::query()
            ->where('effective_from', '<=', $asOf)
            ->max('effective_from');

        if (! $latestEffective) {
            return 0.0;
        }

        $brackets = PayrollTaxBracket::query()
            ->whereDate('effective_from', $latestEffective)
            ->orderBy('sort_order')
            ->get();

        foreach ($brackets as $bracket) {
            $min = (float) $bracket->compensation_min;
            $max = $bracket->compensation_max !== null ? (float) $bracket->compensation_max : null;

            if ($monthlyTaxable < $min) {
                continue;
            }

            if ($max === null || $monthlyTaxable <= $max) {
                $excess = max(0, $monthlyTaxable - $min);
                $tax = (float) $bracket->base_tax + ($excess * (float) $bracket->rate_on_excess);

                return Money::toFloat(Money::round($tax));
            }
        }

        $last = $brackets->last();
        if (! $last) {
            return 0.0;
        }

        $min = (float) $last->compensation_min;
        $excess = max(0, $monthlyTaxable - $min);

        return Money::toFloat(Money::round((float) $last->base_tax + ($excess * (float) $last->rate_on_excess)));
    }

    public function semiMonthlyTax(float $monthlyTaxable, ?string $asOfDate = null): float
    {
        return Money::toFloat(Money::round($this->monthlyTax($monthlyTaxable, $asOfDate) / 2));
    }
}
