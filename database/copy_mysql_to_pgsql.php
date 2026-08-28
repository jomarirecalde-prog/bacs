<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$skip = [
    'migrations',
    'cache',
    'cache_locks',
    'jobs',
    'job_batches',
    'failed_jobs',
    'sessions',
];

$booleanColumns = [
    'attendance' => ['is_manual', 'is_edited'],
    'calendar_events' => ['is_all_day', 'notify_audience'],
    'users' => ['must_change_password'],
    'work_schedules' => ['is_default'],
];

$jsonColumns = [
    'work_schedules' => ['work_days'],
];

$source = DB::connection('mysql');
$target = DB::connection('pgsql');

$ordered = [
    'users',
    'password_reset_tokens',
    'departments',
    'work_schedules',
    'holidays',
    'settings',
    'employees',
    'attendance_stations',
    'calendar_events',
    'attendance',
    'attendance_edits',
    'leaves',
    'employee_qr_tokens',
    'station_activity_logs',
    'station_device_bindings',
    'audit_logs',
    'app_notifications',
    'calendar_event_department',
    'calendar_event_employee',
];

$existing = collect($source->select('SHOW TABLES'))
    ->map(fn ($row) => array_values((array) $row)[0])
    ->reject(fn ($table) => in_array($table, $skip, true))
    ->values();

$tables = collect($ordered)
    ->filter(fn ($table) => $existing->contains($table))
    ->merge($existing->diff($ordered))
    ->values();

echo 'Copying '.$tables->count().' tables from MySQL to Neon...'.PHP_EOL;

foreach ($tables->reverse() as $table) {
    $target->table($table)->delete();
}

foreach ($tables as $table) {
    $count = $source->table($table)->count();

    if ($count === 0) {
        echo "  {$table}: 0 rows".PHP_EOL;
        continue;
    }

    $source->table($table)->orderByRaw('1')->chunk(200, function ($rows) use ($table, $target, $booleanColumns, $jsonColumns) {
        $payload = $rows->map(function ($row) use ($table, $booleanColumns, $jsonColumns) {
            $data = (array) $row;

            foreach ($booleanColumns[$table] ?? [] as $column) {
                if (array_key_exists($column, $data) && $data[$column] !== null) {
                    $data[$column] = (bool) (int) $data[$column];
                }
            }

            foreach ($jsonColumns[$table] ?? [] as $column) {
                if (! array_key_exists($column, $data) || $data[$column] === null || is_array($data[$column])) {
                    continue;
                }

                $decoded = json_decode((string) $data[$column], true);
                $data[$column] = json_last_error() === JSON_ERROR_NONE ? json_encode($decoded) : $data[$column];
            }

            return $data;
        })->all();

        $target->table($table)->insert($payload);
    });

    echo "  {$table}: {$count} rows".PHP_EOL;
}

foreach ($tables as $table) {
    if (! Schema::connection('pgsql')->hasColumn($table, 'id')) {
        continue;
    }

    $target->statement("
        SELECT setval(
            pg_get_serial_sequence('{$table}', 'id'),
            COALESCE((SELECT MAX(id) FROM {$table}), 1)
        )
    ");
}

echo 'Done.'.PHP_EOL;
echo 'Neon users='.$target->table('users')->count()
    .' employees='.$target->table('employees')->count()
    .' departments='.$target->table('departments')->count().PHP_EOL;
