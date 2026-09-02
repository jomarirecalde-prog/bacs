<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\EmployeePhotoStorage;
use Tests\TestCase;

class EmployeePhotoStorageTest extends TestCase
{
    public function test_photo_url_falls_back_when_s3_disk_requested_without_bucket(): void
    {
        config([
            'filesystems.employee_photos_disk' => 's3',
            'filesystems.disks.s3.bucket' => null,
        ]);

        $employee = new Employee([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'photo' => 'photos/employees/1/example.jpg',
        ]);

        $url = app(EmployeePhotoStorage::class)->url($employee);

        $this->assertStringStartsWith('https://ui-avatars.com/api/', $url);
    }

    public function test_disk_falls_back_to_public_when_s3_is_not_configured(): void
    {
        config([
            'filesystems.employee_photos_disk' => 's3',
            'filesystems.disks.s3.bucket' => null,
        ]);

        $this->assertSame('public', app(EmployeePhotoStorage::class)->disk());
    }
}
