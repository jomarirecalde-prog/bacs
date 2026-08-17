<?php

namespace App\Support;

class SecureHash
{
    public static function make(string $value): string
    {
        return hash_hmac('sha256', $value, (string) config('app.key'));
    }

    public static function equals(string $plain, string $hash): bool
    {
        return hash_equals($hash, self::make($plain));
    }

    public static function randomToken(int $bytes = 32): string
    {
        return bin2hex(random_bytes($bytes));
    }
}
