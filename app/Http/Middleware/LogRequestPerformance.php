<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Development-only request profiler. Never attached in production unless
 * APP_DEBUG is on, and never writes SQL bindings or credentials.
 */
class LogRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        $queries = 0;
        $slow = [];
        $slowMs = (int) config('performance.slow_query_ms', 100);

        DB::listen(function ($query) use (&$queries, &$slow, $slowMs) {
            $queries++;
            if ($query->time >= $slowMs) {
                $slow[] = [
                    'ms' => round($query->time, 1),
                    'sql' => $this->summariseSql($query->sql),
                ];
            }
        });

        $started = microtime(true);
        /** @var Response $response */
        $response = $next($request);
        $elapsed = (microtime(true) - $started) * 1000;
        $path = '/'.ltrim($request->path(), '/');

        $requestMs = (int) config('performance.slow_request_ms', 400);
        $queryLimit = (int) config('performance.excessive_queries', 25);

        if ($elapsed >= $requestMs || $queries >= $queryLimit || $slow !== []) {
            Log::channel('performance')->warning('Slow or heavy request', [
                'method' => $request->method(),
                'path' => $path,
                'ms' => (int) round($elapsed),
                'queries' => $queries,
                'slow_queries' => $slow,
                'partial' => $request->headers->get('X-BACS-Partial') === '1',
            ]);
        }

        if (config('app.debug')) {
            $response->headers->set('X-Request-Time-Ms', (string) (int) round($elapsed));
            $response->headers->set('X-Query-Count', (string) $queries);
        }

        return $response;
    }

    private function enabled(): bool
    {
        if (app()->runningInConsole() && ! app()->runningUnitTests()) {
            return false;
        }

        return (bool) config('performance.log', app()->isLocal() || config('app.debug'));
    }

    private function summariseSql(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql) ?? $sql;

        return mb_substr($sql, 0, 240);
    }
}
