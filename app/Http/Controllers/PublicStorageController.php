<?php

namespace App\Http\Controllers;

use App\Services\EmployeePhotoStorage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicStorageController extends Controller
{
    public function show(string $path, EmployeePhotoStorage $photos): StreamedResponse
    {
        abort_unless($photos->disk() === 'public', 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path);
    }
}
