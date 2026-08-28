<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Request / query profiling
    |--------------------------------------------------------------------------
    |
    | Warnings are written to the `performance` log channel. Bindings and
    | credentials are never logged. Production stays quiet unless debug is on.
    |
    */

    'log' => filter_var(env('PERF_LOG', env('APP_DEBUG', false)), FILTER_VALIDATE_BOOLEAN),

    'slow_request_ms' => (int) env('PERF_SLOW_REQUEST_MS', 400),

    'slow_query_ms' => (int) env('PERF_SLOW_QUERY_MS', 100),

    'excessive_queries' => (int) env('PERF_EXCESSIVE_QUERIES', 25),

];
