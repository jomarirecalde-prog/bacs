<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Minimal Vercel Blob REST client for serverless photo storage.
 *
 * @see https://vercel.com/docs/vercel-blob
 */
class VercelBlobClient
{
    private const API_BASE = 'https://blob.vercel-storage.com';

    private const API_VERSION = '7';

    public function configured(): bool
    {
        return filled($this->token());
    }

    /**
     * @return array{url: string, pathname: string, contentType: ?string}
     */
    public function put(string $pathname, string $contents, string $contentType = 'application/octet-stream'): array
    {
        $pathname = ltrim($pathname, '/');

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->requireToken(),
            'x-api-version' => self::API_VERSION,
            'x-content-type' => $contentType,
            'x-vercel-blob-access' => 'public',
        ])
            ->withBody($contents, $contentType)
            ->timeout(25)
            ->put(self::API_BASE.'/'.$pathname);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Vercel Blob upload failed (HTTP '.$response->status().').'
            );
        }

        $body = $response->json();
        $url = $body['url'] ?? null;

        if (! is_string($url) || $url === '') {
            throw new RuntimeException('Vercel Blob upload returned no URL.');
        }

        return [
            'url' => $url,
            'pathname' => (string) ($body['pathname'] ?? $pathname),
            'contentType' => isset($body['contentType']) ? (string) $body['contentType'] : $contentType,
        ];
    }

    public function delete(string $urlOrPathname): void
    {
        if ($urlOrPathname === '') {
            return;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->requireToken(),
            'x-api-version' => self::API_VERSION,
        ])
            ->asJson()
            ->timeout(15)
            ->post(self::API_BASE.'/delete', [
                'urls' => [$urlOrPathname],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                'Vercel Blob delete failed (HTTP '.$response->status().').'
            );
        }
    }

    public function exists(string $url): bool
    {
        if ($url === '' || ! str_starts_with($url, 'http')) {
            return false;
        }

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->requireToken(),
            'x-api-version' => self::API_VERSION,
        ])
            ->timeout(10)
            ->get(self::API_BASE.'?url='.urlencode($url));

        return $response->successful();
    }

    public static function isBlobUrl(?string $value): bool
    {
        if (! filled($value)) {
            return false;
        }

        return str_contains($value, '.blob.vercel-storage.com/')
            || str_starts_with($value, 'https://blob.vercel-storage.com/');
    }

    private function token(): ?string
    {
        $token = config('filesystems.vercel_blob_token')
            ?? env('BLOB_READ_WRITE_TOKEN');

        return filled($token) ? (string) $token : null;
    }

    private function requireToken(): string
    {
        $token = $this->token();

        if (! $token) {
            throw new RuntimeException(
                'BLOB_READ_WRITE_TOKEN is not configured. Create a Vercel Blob store and link it to this project.'
            );
        }

        return $token;
    }
}
