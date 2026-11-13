<?php

namespace App\Support;

final class MinuteDuration
{
    public static function label(int $minutes): string
    {
        $minutes = max(0, $minutes);
        $hours = intdiv($minutes, 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return "{$hours} hr {$mins} min";
        }

        if ($hours > 0) {
            return $hours === 1 ? '1 hour' : "{$hours} hours";
        }

        return "{$mins} min";
    }

    public static function labelOrNull(int $minutes): ?string
    {
        return $minutes > 0 ? self::label($minutes) : null;
    }
}
