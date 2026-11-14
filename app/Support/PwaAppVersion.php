<?php

namespace App\Support;

/**
 * Installed PWA version shown in the UI — mirrors package.json and public/sw-app.js.
 */
final class PwaAppVersion
{
    private static ?string $version = null;

    public static function version(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }

        $path = base_path('package.json');

        if (! is_readable($path)) {
            return self::$version = '0.0.0';
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! isset($data['version']) || ! is_string($data['version'])) {
            return self::$version = '0.0.0';
        }

        return self::$version = $data['version'];
    }
}