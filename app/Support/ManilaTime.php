<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

class ManilaTime
{
    public const TIMEZONE = 'Asia/Manila';

    public static function now(): Carbon
    {
        return Carbon::now(self::TIMEZONE);
    }

    public static function today(): Carbon
    {
        return self::now()->startOfDay();
    }

    public static function todayDate(): string
    {
        return self::now()->toDateString();
    }

    public static function parse(mixed $value): Carbon
    {
        return Carbon::parse($value, self::TIMEZONE);
    }

    public static function immutableNow(): CarbonImmutable
    {
        return CarbonImmutable::now(self::TIMEZONE);
    }

    public static function combineDateAndTime(string $date, string $time): Carbon
    {
        return Carbon::parse("{$date} {$time}", self::TIMEZONE);
    }

    public static function formatTime(?Carbon $value, string $format = 'h:i A'): ?string
    {
        return $value?->timezone(self::TIMEZONE)->format($format);
    }

    public static function formatDateTime(?Carbon $value, string $format = 'F j, Y g:i A'): ?string
    {
        return $value?->timezone(self::TIMEZONE)->format($format);
    }

    public static function formatDate(?Carbon $value, string $format = 'F j, Y'): ?string
    {
        return $value?->timezone(self::TIMEZONE)->format($format);
    }
}
