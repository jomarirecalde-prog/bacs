<?php

namespace Tests\Unit;

use App\Services\VercelBlobClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class VercelBlobClientTest extends TestCase
{
    public function test_put_returns_public_url(): void
    {
        config(['filesystems.vercel_blob_token' => 'test-token']);

        Http::fake([
            'blob.vercel-storage.com/*' => Http::response([
                'url' => 'https://example.public.blob.vercel-storage.com/photos/a.jpg',
                'pathname' => 'photos/a.jpg',
                'contentType' => 'image/jpeg',
            ], 200),
        ]);

        $result = app(VercelBlobClient::class)->put('photos/a.jpg', 'jpeg-bytes', 'image/jpeg');

        $this->assertSame('https://example.public.blob.vercel-storage.com/photos/a.jpg', $result['url']);
        Http::assertSent(function ($request) {
            return $request->method() === 'PUT'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request->hasHeader('x-vercel-blob-access', 'public');
        });
    }

    public function test_put_without_token_fails_clearly(): void
    {
        config(['filesystems.vercel_blob_token' => null]);
        putenv('BLOB_READ_WRITE_TOKEN=');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('BLOB_READ_WRITE_TOKEN');

        app(VercelBlobClient::class)->put('photos/a.jpg', 'x', 'image/jpeg');
    }
}
