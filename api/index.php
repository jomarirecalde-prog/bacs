<?php

/**
 * Serverless entry point for Vercel (vercel-php).
 */
$storage = '/tmp/storage';
foreach ([
    $storage.'/app/public',
    $storage.'/app/private',
    $storage.'/framework/cache/data',
    $storage.'/framework/sessions',
    $storage.'/framework/views',
    $storage.'/logs',
] as $dir) {
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$sqlite = '/tmp/database.sqlite';
$seed = __DIR__.'/../database/vercel.sqlite';
$needsSeed = ! file_exists($sqlite) || filesize($sqlite) < 1024;
if (! $needsSeed && file_exists($sqlite)) {
    try {
        $pdo = new PDO('sqlite:'.$sqlite);
        $needsSeed = ! $pdo->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='calendar_events'")->fetchColumn();
    } catch (Throwable) {
        $needsSeed = true;
    }
}
if ($needsSeed && file_exists($seed)) {
    copy($seed, $sqlite);
}

require __DIR__.'/../public/index.php';
