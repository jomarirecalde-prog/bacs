<?php

namespace App\Http\Controllers;

use App\Services\EmployeePhotoStorage;
use App\Services\SensitiveAccessLogger;
use App\Support\PrivateStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePhotoController extends Controller
{
    public function __construct(private readonly SensitiveAccessLogger $sensitiveAccess) {}

    public function show(Request $request, string $path, EmployeePhotoStorage $photos): StreamedResponse
    {
        abort_unless(str_starts_with($path, 'photos/employees/'), 404);

        $disk = $photos->disk();

        if ($disk === PrivateStorage::DISK) {
            abort_unless(PrivateStorage::exists($path), 404);
            $this->logPhotoAccess($request, $path);

            return PrivateStorage::streamResponse($path);
        }

        abort_if($disk === 'public', 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        $this->logPhotoAccess($request, $path);

        return Storage::disk($disk)->response($path);
    }

    private function logPhotoAccess(Request $request, string $path): void
    {
        if (auth('station')->check()) {
            return;
        }

        $user = $request->user();
        if (! $user) {
            return;
        }

        $employeeId = null;
        if (preg_match('#^photos/employees/(\d+)/#', $path, $matches)) {
            $employeeId = (int) $matches[1];
        }

        $this->sensitiveAccess->fileDownload(
            $user,
            'EmployeePhotos',
            $employeeId,
            'Employee profile photo viewed.',
            $request,
            ['path' => $path],
        );
    }
}
