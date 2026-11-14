<?php

namespace App\Http\Controllers;

use App\Services\EmployeePhotoStorage;
use App\Support\PrivateStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EmployeePhotoController extends Controller
{
    public function show(Request $request, string $path, EmployeePhotoStorage $photos): StreamedResponse
    {
        abort_unless(str_starts_with($path, 'photos/employees/'), 404);

        $disk = $photos->disk();

        if ($disk === PrivateStorage::DISK) {
            abort_unless(PrivateStorage::exists($path), 404);

            return PrivateStorage::streamResponse($path);
        }

        abort_if($disk === 'public', 404);
        abort_unless(Storage::disk($disk)->exists($path), 404);

        return Storage::disk($disk)->response($path);
    }
}