<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Development / PERF_LOG request profiler.
 * Never logs SQL bindings or credentials. Safe Server-Timing headers omit SQL text.
 */
class LogRequestPerformance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->enabled()) {
            return $next($request);
        }

        $queries = 0;
        $queryMs = 0.0;
        $slow = [];
        $slowMs = (int) config('performance.slow_query_ms', 100);
        $phases = [
            'boot_ms' => (int) round((microtime(true) - LARAVEL_START) * 1000),
        ];

        DB::listen(function ($query) use (&$queries, &$queryMs, &$slow, $slowMs) {
            $queries++;
            $queryMs += $query->time;
            if ($query->time >= $slowMs) {
                $slow[] = [
                    'ms' => round($query->time, 1),
                    'sql' => $this->summariseSql($query->sql),
                ];
            }
        });

        $started = microtime(true);

        // First touch establishes the PDO link — isolate connect cost from query work.
        $connectMs = null;
        try {
            $tConnect = microtime(true);
            DB::connection()->getPdo();
            $connectMs = (int) round((microtime(true) - $tConnect) * 1000);
        } catch (\Throwable) {
            $connectMs = null;
        }

        /** @var Response $response */
        $response = $next($request);
        $elapsed = (microtime(true) - $started) * 1000;
        $path = '/'.ltrim($request->path(), '/');

        $phases['db_connect_ms'] = $connectMs;
        $phases['db_query_ms'] = (int) round($queryMs);
        $phases['db_query_count'] = $queries;
        $phases['handler_ms'] = (int) round($elapsed);
        $phases['total_ms'] = (int) round(((microtime(true) - LARAVEL_START) * 1000));

        $requestMs = (int) config('performance.slow_request_ms', 400);
        $queryLimit = (int) config('performance.excessive_queries', 25);

        if ($elapsed >= $requestMs || $queries >= $queryLimit || $slow !== []) {
            Log::channel('performance')->warning('Slow or heavy request', [
                'method' => $request->method(),
                'path' => $path,
                'ms' => (int) round($elapsed),
                'queries' => $queries,
                'query_ms' => (int) round($queryMs),
                'db_connect_ms' => $connectMs,
                'boot_ms' => $phases['boot_ms'],
                'slow_queries' => $slow,
                'partial' => $request->headers->get('X-BACS-Partial') === '1',
            ]);
        }

        if (config('app.debug') || config('performance.expose_headers')) {
            $response->headers->set('X-Request-Time-Ms', (string) $phases['handler_ms']);
            $response->headers->set('X-Query-Count', (string) $queries);
            $response->headers->set('X-Db-Connect-Ms', (string) ($connectMs ?? ''));
            $response->headers->set('X-Db-Query-Ms', (string) $phases['db_query_ms']);
            $response->headers->set(
                'Server-Timing',
                sprintf(
                    'boot;dur=%d, db_connect;dur=%d, db_query;dur=%d, app;dur=%d, total;dur=%d',
                    $phases['boot_ms'],
                    $connectMs ?? 0,
                    $phases['db_query_ms'],
                    $phases['handler_ms'],
                    $phases['total_ms']
                )
            );
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
