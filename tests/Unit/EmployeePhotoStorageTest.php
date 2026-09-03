<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\EmployeePhotoStorage;
use App\Services\VercelBlobClient;
use Tests\TestCase;

class EmployeePhotoStorageTest extends TestCase
{
    public function test_photo_url_falls_back_when_s3_disk_requested_without_bucket(): void
    {
        config([
            'filesystems.employee_photos_disk' => 's3',
            'filesystems.disks.s3.bucket' => null,
            'filesystems.disks.s3.key' => null,
            'filesystems.disks.s3.secret' => null,
            'filesystems.vercel_blob_token' => null,
        ]);

        $employee = new Employee([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'photo' => 'photos/employees/1/example.jpg',
        ]);

        $url = app(EmployeePhotoStorage::class)->url($employee);

        $this->assertStringStartsWith('https://ui-avatars.com/api/', $url);
    }

    public function test_disk_falls_back_to_public_when_neither_s3_nor_blob_configured(): void
    {
        config([
            'filesystems.employee_photos_disk' => 's3',
            'filesystems.disks.s3.bucket' => null,
            'filesystems.disks.s3.key' => null,
            'filesystems.disks.s3.secret' => null,
            'filesystems.vercel_blob_token' => null,
        ]);

        $this->assertSame('public', app(EmployeePhotoStorage::class)->disk());
    }

    public function test_disk_prefers_vercel_blob_when_token_is_set_and_s3_is_not(): void
    {
        config([
            'filesystems.employee_photos_disk' => null,
            'filesystems.disks.s3.bucket' => null,
            'filesystems.disks.s3.key' => null,
            'filesystems.disks.s3.secret' => null,
            'filesystems.vercel_blob_token' => 'vercel_blob_rw_test_token',
        ]);

        $this->assertSame('vercel_blob', app(EmployeePhotoStorage::class)->disk());
    }

    public function test_absolute_blob_url_is_returned_as_is(): void
    {
        $url = 'https://example.public.blob.vercel-storage.com/photos/employees/1/a.jpg';

        $employee = new Employee([
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'photo' => $url,
        ]);

        $this->assertSame($url, app(EmployeePhotoStorage::class)->url($employee));
        $this->assertTrue(VercelBlobClient::isBlobUrl($url));
    }
}
