<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeePhotoStorage
{
    private const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp'];

    public function disk(): string
    {
        $configured = config('filesystems.employee_photos_disk');

        if (filled($configured)) {
            return $configured;
        }

        return filled(config('filesystems.disks.s3.bucket')) ? 's3' : 'public';
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
        $extension = match ($file->getMimeType()) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = Str::uuid()->toString().'.'.$extension;

        return $file->storeAs($directory, $filename, $this->disk());
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
