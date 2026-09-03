<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EmployeePhotoStorage
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    /** Longest edge for stored profile photos (keeps pages light on mobile). */
    private const MAX_EDGE = 512;

    private const JPEG_QUALITY = 82;

    private function s3Configured(): bool
    {
        $disk = config('filesystems.disks.s3', []);

        return filled($disk['bucket'] ?? null)
            && filled($disk['key'] ?? null)
            && filled($disk['secret'] ?? null)
            && filled($disk['region'] ?? null);
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

    /**
     * On Vercel, local/public disks are ephemeral (/tmp). Photos must use S3.
     */
    public function assertWritable(): void
    {
        $disk = $this->disk();
        $onVercel = (($_ENV['VERCEL'] ?? getenv('VERCEL')) === '1')
            || str_contains((string) config('app.url'), 'vercel.app');

        if ($onVercel && $disk !== 's3') {
            throw new RuntimeException(
                'Profile photos require S3 on Vercel. Set AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, and AWS_BUCKET in the Vercel project environment.'
            );
        }
    }

    public function store(Employee $employee, UploadedFile $file): string
    {
        $this->assertWritable();

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

        if ($binary === false || $binary === '') {
            throw new RuntimeException('Could not read the uploaded photo.');
        }

        try {
            $ok = Storage::disk($this->disk())->put($path, $binary, [
                'visibility' => 'public',
                'ContentType' => 'image/jpeg',
            ]);
        } catch (Throwable $e) {
            Log::error('Employee photo upload failed', [
                'disk' => $this->disk(),
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException(
                $this->disk() === 's3'
                    ? 'Could not upload the photo to storage. Check the S3 credentials and bucket permissions.'
                    : 'Could not store the photo on the server.',
                previous: $e
            );
        }

        if ($ok === false) {
            throw new RuntimeException(
                $this->disk() === 's3'
                    ? 'Could not upload the photo to storage. Check the S3 credentials and bucket permissions.'
                    : 'Could not store the photo on the server.'
            );
        }

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
        if (! $path) {
            return;
        }

        try {
            Storage::disk($this->disk())->delete($path);
        } catch (Throwable $e) {
            Log::warning('Employee photo delete failed', [
                'path' => $path,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function exists(?string $path): bool
    {
        if (! filled($path)) {
            return false;
        }

        try {
            return Storage::disk($this->disk())->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    public function url(Employee $employee): string
    {
        if (! filled($employee->photo)) {
            return $this->placeholder($employee);
        }

        try {
            if ($this->disk() === 's3') {
                return Storage::disk('s3')->url($employee->photo);
            }

            if (! Storage::disk('public')->exists($employee->photo)) {
                return $this->placeholder($employee);
            }

            return asset('storage/'.$employee->photo);
        } catch (Throwable) {
            return $this->placeholder($employee);
        }
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
