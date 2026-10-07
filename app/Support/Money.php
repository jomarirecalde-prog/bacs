<?php

namespace App\Support;

final class Money
{
    public static function round(float|string|null $amount, int $precision = 2): string
    {
        $value = (float) ($amount ?? 0);

        return number_format(round($value, $precision, PHP_ROUND_HALF_UP), $precision, '.', '');
    }

    public static function toFloat(string|float|null $amount): float
    {
        return (float) self::round($amount);
    }
}
