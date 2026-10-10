<?php

namespace App\Services\Payroll;

use App\Support\Money;
use App\Support\PayrollDeductionConfig;

class AttendanceDeductionService
{
    /**
     * @param  array{daily_rate: float, hourly_rate: float, working_hours: int}  $rates
     * @return array{
     *     absence: float,
     *     late: float,
     *     undertime: float,
     *     minute_rate: float,
     *     meta: array{late: array<string, mixed>, undertime: array<string, mixed>, absence: array<string, mixed>}
     * }
     */
    public function calculate(
        int $absentDays,
        int $lateMinutes,
        int $undertimeMinutes,
        array $rates,
        string $asOfDate,
    ): array {
        $config = PayrollDeductionConfig::forDate($asOfDate);

        $minuteRate = $this->derivedMinuteRate($rates, $config['late']['minute_rate_from']);

        $absence = $config['absence']['enabled']
            ? Money::toFloat(Money::round($absentDays * $rates['daily_rate']))
            : 0.0;

        $lateResult = $this->minutesDeduction(
            $lateMinutes,
            $config['late'],
            $rates,
            $minuteRate,
        );

        $undertimeResult = $this->minutesDeduction(
            $undertimeMinutes,
            $config['undertime'],
            $rates,
            $this->derivedMinuteRate($rates, $config['undertime']['minute_rate_from']),
        );

        return [
            'absence' => $absence,
            'late' => $lateResult['amount'],
            'undertime' => $undertimeResult['amount'],
            'minute_rate' => $minuteRate,
            'meta' => [
                'absence' => [
                    'days' => $absentDays,
                    'daily_rate' => $rates['daily_rate'],
                    'enabled' => $config['absence']['enabled'],
                ],
                'late' => $lateResult['meta'],
                'undertime' => $undertimeResult['meta'],
            ],
        ];
    }

    /**
     * @param  array{daily_rate: float, hourly_rate: float, working_hours: int}  $rates
     * @param  array<string, mixed>  $rule
     * @return array{amount: float, meta: array<string, mixed>}
     */
    private function minutesDeduction(int $minutes, array $rule, array $rates, float $minuteRate): array
    {
        if (! $rule['enabled'] || $minutes <= 0) {
            return [
                'amount' => 0.0,
                'meta' => [
                    'minutes' => $minutes,
                    'billable_minutes' => 0,
                    'calculation_mode' => $rule['calculation_mode'],
                    'enabled' => $rule['enabled'],
                ],
            ];
        }

        $threshold = (int) $rule['minimum_billable_minutes'];
        $billable = $minutes >= $threshold ? $minutes : 0;
        $billable = $this->applyMinuteRounding($billable, (string) $rule['round_minutes']);

        $amount = match ($rule['calculation_mode']) {
            'fixed_per_minute' => $billable * (float) $rule['fixed_per_minute'],
            'fixed_per_block' => $this->blockAmount(
                $billable,
                (int) $rule['block_minutes'],
                (float) $rule['amount_per_block'],
            ),
            default => $billable * $minuteRate,
        };

        return [
            'amount' => Money::toFloat(Money::round($amount)),
            'meta' => [
                'minutes' => $minutes,
                'billable_minutes' => $billable,
                'calculation_mode' => $rule['calculation_mode'],
                'minute_rate' => $rule['calculation_mode'] === 'derived_minute_rate' ? $minuteRate : null,
                'fixed_per_minute' => $rule['calculation_mode'] === 'fixed_per_minute' ? (float) $rule['fixed_per_minute'] : null,
                'block_minutes' => $rule['calculation_mode'] === 'fixed_per_block' ? (int) $rule['block_minutes'] : null,
                'amount_per_block' => $rule['calculation_mode'] === 'fixed_per_block' ? (float) $rule['amount_per_block'] : null,
                'minimum_billable_minutes' => (int) $rule['minimum_billable_minutes'],
                'round_minutes' => (string) $rule['round_minutes'],
                'hourly_rate' => $rates['hourly_rate'],
                'daily_rate' => $rates['daily_rate'],
                'enabled' => true,
            ],
        ];
    }

    /**
     * @param  array{daily_rate: float, hourly_rate: float, working_hours: int}  $rates
     */
    private function derivedMinuteRate(array $rates, string $minuteRateFrom): float
    {
        if ($minuteRateFrom === 'daily' && $rates['working_hours'] > 0) {
            return $rates['daily_rate'] / $rates['working_hours'] / 60;
        }

        return $rates['hourly_rate'] > 0 ? $rates['hourly_rate'] / 60 : 0.0;
    }

    private function blockAmount(int $billableMinutes, int $blockMinutes, float $amountPerBlock): float
    {
        if ($billableMinutes <= 0 || $blockMinutes <= 0 || $amountPerBlock <= 0) {
            return 0.0;
        }

        $blocks = (int) ceil($billableMinutes / $blockMinutes);

        return $blocks * $amountPerBlock;
    }

    private function applyMinuteRounding(int $minutes, string $mode): int
    {
        return match ($mode) {
            'ceil' => (int) ceil($minutes),
            'floor' => (int) floor($minutes),
            'nearest' => (int) round($minutes),
            default => $minutes,
        };
    }
}
