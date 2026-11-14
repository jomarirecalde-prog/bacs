<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');
        $cameraPolicy = $request->is('attendance-station', 'attendance-station/*')
            ? 'camera=(self)'
            : 'camera=()';
        $geolocationPolicy = $request->is('attendance-station', 'attendance-station/*')
            ? 'geolocation=(self)'
            : 'geolocation=()';
        $response->headers->set(
            'Permissions-Policy',
            "{$cameraPolicy}, microphone=(), {$geolocationPolicy}"
        );
        $viteDevSources = $this->viteDevServerCspSources();

        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; object-src 'none'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval'{$viteDevSources}; worker-src 'self' blob:; child-src 'self' blob:; "
            ."style-src 'self' 'unsafe-inline' https://fonts.bunny.net{$viteDevSources}; "
            ."img-src 'self' data: https: blob:; font-src 'self' data: https://fonts.bunny.net; connect-src 'self' https: wss: ws:{$viteDevSources};"
        );

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Allow Laravel Vite dev server assets in local development (see public/hot).
     */
    private function viteDevServerCspSources(): string
    {
        if (! app()->environment('local')) {
            return '';
        }

        $hotPath = public_path('hot');
        if (! is_readable($hotPath)) {
            return '';
        }

        $url = trim((string) file_get_contents($hotPath));
        if ($url === '') {
            return '';
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return '';
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : 5173;
        $portSuffix = ':'.$port;
        $scheme = $parts['scheme'];

        $host = $parts['host'];
        if ($host === '[::1]' || $host === '::1') {
            $hosts = ['127.0.0.1', 'localhost'];
        } elseif ($host === '127.0.0.1' || $host === 'localhost') {
            $hosts = ['127.0.0.1', 'localhost'];
        } else {
            $hosts = [$host];
        }

        $sources = [];
        foreach ($hosts as $cspHost) {
            $sources[] = $scheme.'://'.$cspHost.$portSuffix;
        }

        return ' '.implode(' ', array_unique($sources));
    }
}
