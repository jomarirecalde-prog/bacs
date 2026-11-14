<?php

namespace App\Support;

use App\Models\Setting;

final class MapboxSettings
{
    public const DEFAULT_STYLE = 'mapbox://styles/mapbox/streets-v12';

    /**
     * @return array{
     *     token_configured: bool,
     *     token_hint: string|null,
     *     style: string,
     *     using_database: bool,
     *     using_env: bool,
     * }
     */
    public static function formState(): array
    {
        $token = static::accessToken();
        $usingDatabase = Setting::has('mapbox_access_token')
            && trim((string) Setting::get('mapbox_access_token')) !== '';

        return [
            'token_configured' => $token !== null && $token !== '',
            'token_hint' => static::tokenHint($token),
            'style' => static::style(),
            'using_database' => $usingDatabase,
            'using_env' => ! $usingDatabase && $token !== null && $token !== '',
        ];
    }

    public static function accessToken(): ?string
    {
        if (Setting::has('mapbox_access_token')) {
            $fromDb = trim((string) Setting::get('mapbox_access_token'));
            if ($fromDb !== '') {
                return $fromDb;
            }
        }

        $fromConfig = config('services.mapbox.access_token');
        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return trim($fromConfig);
        }

        foreach (['MAPBOX_ACCESS_TOKEN'] as $key) {
            $fromEnv = static::environmentValue($key);
            if ($fromEnv !== null) {
                return $fromEnv;
            }
        }

        return static::dotEnvValue('MAPBOX_ACCESS_TOKEN');
    }

    public static function style(): string
    {
        if (Setting::has('mapbox_style')) {
            $fromDb = trim((string) Setting::get('mapbox_style'));
            if ($fromDb !== '') {
                return $fromDb;
            }
        }

        $fromConfig = config('services.mapbox.style');
        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return trim($fromConfig);
        }

        $fromEnv = static::environmentValue('MAPBOX_STYLE');
        if ($fromEnv !== null) {
            return $fromEnv;
        }

        $fromFile = static::dotEnvValue('MAPBOX_STYLE');

        return $fromFile ?? self::DEFAULT_STYLE;
    }

    private static function tokenHint(?string $token): ?string
    {
        if ($token === null || $token === '') {
            return null;
        }

        if (strlen($token) <= 8) {
            return '••••';
        }

        return '••••'.substr($token, -4);
    }

    private static function environmentValue(string $key): ?string
    {
        $candidates = [
            $_ENV[$key] ?? null,
            $_SERVER[$key] ?? null,
            getenv($key) !== false ? getenv($key) : null,
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private static function dotEnvValue(string $key): ?string
    {
        $path = base_path('.env');
        if (! is_readable($path)) {
            return null;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return null;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            if (! str_contains($line, '=')) {
                continue;
            }
            [$name, $value] = explode('=', $line, 2);
            if (trim($name) !== $key) {
                continue;
            }
            $value = trim($value);
            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }
            $value = trim($value);

            return $value !== '' ? $value : null;
        }

        return null;
    }
}