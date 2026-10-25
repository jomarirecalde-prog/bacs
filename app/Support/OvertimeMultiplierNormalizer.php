<?php

namespace App\Support;

final class OvertimeMultiplierNormalizer
{
    /**
     * Accepts multiplier (1.25) or percentage string/number (125, "125%").
     *
     * @return array{multiplier: float, percentage: float}
     */
    public static function normalize(string|float|int $input): array
    {
        if (is_string($input)) {
            $trimmed = trim($input);
            if (str_ends_with($trimmed, '%')) {
                $percentage = (float) rtrim($trimmed, '% %');

                return self::fromPercentage($percentage);
            }

            $numeric = (float) $trimmed;

            return self::fromNumeric($numeric);
        }

        return self::fromNumeric((float) $input);
    }

    /**
     * @return array{multiplier: float, percentage: float}
     */
    public static function fromNumeric(float $value): array
    {
        if ($value >= 10) {
            return self::fromPercentage($value);
        }

        $multiplier = round($value, 4);
        $percentage = round($multiplier * 100, 4);

        return ['multiplier' => $multiplier, 'percentage' => $percentage];
    }

    /**
     * @return array{multiplier: float, percentage: float}
     */
    public static function fromPercentage(float $percentage): array
    {
        $percentage = round($percentage, 4);
        $multiplier = round($percentage / 100, 4);

        return ['multiplier' => $multiplier, 'percentage' => $percentage];
    }
}
