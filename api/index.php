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
if ((! file_exists($sqlite) || filesize($sqlite) < 1024) && file_exists($seed)) {
    copy($seed, $sqlite);
}

require __DIR__.'/../public/index.php';
