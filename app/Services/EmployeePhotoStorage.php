<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeePhotoStorage
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Longest edge for stored profile photos (keeps pages light on mobile). */
    private const MAX_EDGE = 512;

    private const JPEG_QUALITY = 82;

    private function s3Configured(): bool
    {
        return filled(config('filesystems.disks.s3.bucket'));
    }

    public function disk(): string
    {
        $configured = config('filesystems.employee_photos_disk');

        if ($configured === 's3') {
            return $this->s3Configured() ? 's3' : 'public';
        }

        if (filled($configured)) {
            return $configured;
        }

        return $this->s3Configured() ? 's3' : 'public';
    }

    public function store(Employee $employee, UploadedFile $file): string
    {
        $directory = $employee->exists
            ? 'photos/employees/'.$employee->id
            : 'photos';

        return $this->storeInDirectory($directory, $file);
    }

    private function storeInDirectory(string $directory, UploadedFile $file): string
    {
        $filename = Str::uuid()->toString().'.jpg';
        $path = trim($directory, '/').'/'.$filename;
        $binary = $this->optimizedJpeg($file) ?? file_get_contents($file->getRealPath());

        Storage::disk($this->disk())->put($path, $binary, [
            'visibility' => 'public',
            'ContentType' => 'image/jpeg',
        ]);

        return $path;
    }

    /**
     * Downscale and recompress when GD is available. Falls back to the original
     * bytes so uploads never fail solely because of image processing.
     */
    private function optimizedJpeg(UploadedFile $file): ?string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            return null;
        }

        $raw = @file_get_contents($file->getRealPath());
        if ($raw === false || $raw === '') {
            return null;
        }

        $source = @imagecreatefromstring($raw);
        if (! $source) {
            return null;
        }

        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            imagedestroy($source);

            return null;
        }

        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        if ($scale < 1) {
            $canvas = imagecreatetruecolor($targetW, $targetH);
            if (! $canvas) {
                imagedestroy($source);

                return null;
            }
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
            imagedestroy($source);
            $source = $canvas;
        }

        ob_start();
        imagejpeg($source, null, self::JPEG_QUALITY);
        $jpeg = ob_get_clean();
        imagedestroy($source);

        return is_string($jpeg) && $jpeg !== '' ? $jpeg : null;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk($this->disk())->delete($path);
        }
    }

    public function exists(?string $path): bool
    {
        return filled($path) && Storage::disk($this->disk())->exists($path);
    }

    public function url(Employee $employee): string
    {
        if (! filled($employee->photo)) {
            return $this->placeholder($employee);
        }

        if ($this->disk() === 's3') {
            return Storage::disk('s3')->url($employee->photo);
        }

        if (! Storage::disk('public')->exists($employee->photo)) {
            return $this->placeholder($employee);
        }

        return asset('storage/'.$employee->photo);
    }

    public function hasPhoto(Employee $employee): bool
    {
        return $this->exists($employee->photo);
    }

    public function placeholder(Employee $employee): string
    {
        return 'https://ui-avatars.com/api/?name='.urlencode($employee->fullName()).'&background=047857&color=fff';
    }

    /**
     * @return list<string>
     */
    public static function allowedMimes(): array
    {
        return self::ALLOWED_MIMES;
    }
}
