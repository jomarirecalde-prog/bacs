<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sensitive uploads (leave signatures, travel attachments, employee photos).
 * New files use the private local disk; legacy public-disk paths remain readable for migration.
 */
final class PrivateStorage
{
    public const DISK = 'local';

    public static function exists(string $path): bool
    {
        return Storage::disk(self::DISK)->exists($path)
            || Storage::disk('public')->exists($path);
    }

    public static function get(string $path): ?string
    {
        if (Storage::disk(self::DISK)->exists($path)) {
            return Storage::disk(self::DISK)->get($path);
        }

        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->get($path);
        }

        return null;
    }

    public static function diskForExisting(string $path): ?string
    {
        if (Storage::disk(self::DISK)->exists($path)) {
            return self::DISK;
        }

        if (Storage::disk('public')->exists($path)) {
            return 'public';
        }

        return null;
    }

    public static function downloadResponse(string $path, ?string $filename = null): StreamedResponse
    {
        $disk = self::diskForExisting($path);
        abort_unless($disk, 404);

        return Storage::disk($disk)->download($path, $filename);
    }

    public static function streamResponse(string $path): StreamedResponse
    {
        $disk = self::diskForExisting($path);
        abort_unless($disk, 404);

        return Storage::disk($disk)->response($path);
    }
}
