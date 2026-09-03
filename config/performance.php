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

    /*
    | Short-lived cache for admin dashboard aggregates (present/late/absent).
    | Attendance punches flush this key immediately. Live punches still win via
    | Echo; this only reduces repeated full-roster classification under load.
    */
    'dashboard_snapshot_ttl' => (int) env('PERF_DASHBOARD_SNAPSHOT_TTL', 15),

    /* Hard cap for report Excel/CSV/PDF exports to protect memory. */
    'report_export_max_rows' => (int) env('PERF_REPORT_EXPORT_MAX_ROWS', 5000),

];
