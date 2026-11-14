<?php

declare(strict_types=1);

$in = $argv[1] ?? '';
$out = $argv[2] ?? '';
if ($in === '' || $out === '') {
    fwrite(STDERR, "Usage: prepare_sql_dump.php <in.sql> <out.sql>\n");
    exit(1);
}
$sql = file_get_contents($in);
if ($sql === false) {
    exit(1);
}
$sql = preg_replace('/\/\*M![\s\S]*?\*\//', '', $sql) ?? $sql;
file_put_contents($out, $sql);
