<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = static::allCached();

        return $settings[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, static::allCached());
    }

    /**
     * @return array<string, mixed>
     */
    private static function allCached(): array
    {
        return Cache::remember('app_settings', 60, function () {
            return static::query()->pluck('value', 'key')->all();
        });
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('app_settings');
    }
}
