<?php

declare(strict_types=1);

$host = '127.0.0.1';
$user = 'root';
$pass = 'password';
$database = 'bacs_db';
$sqlFile = __DIR__ . '/../docs/u899628465_bacs.sql';
$newPassword = 'password';

if (! is_file($sqlFile)) {
    fwrite(STDERR, "SQL file not found: {$sqlFile}\n");
    exit(1);
}

$hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

$mysqli = new mysqli($host, $user, $pass);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'Connect failed: ' . $mysqli->connect_error . "\n");
    exit(1);
}

echo "Recreating database {$database}...\n";
$mysqli->query('DROP DATABASE IF EXISTS `' . $mysqli->real_escape_string($database) . '`');
if (! $mysqli->query(
    'CREATE DATABASE `' . $mysqli->real_escape_string($database) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
)) {
    fwrite(STDERR, 'Create database failed: ' . $mysqli->error . "\n");
    exit(1);
}

$mysqli->select_db($database);
$mysqli->set_charset('utf8mb4');

echo 'Importing SQL (' . round(filesize($sqlFile) / 1024 / 1024, 2) . " MB)...\n";
$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "Failed to read SQL file.\n");
    exit(1);
}

$importSql = dirname($sqlFile) . '/' . pathinfo($sqlFile, PATHINFO_FILENAME) . '.import.sql';
$sql = preg_replace('/\/\*M![\s\S]*?\*\//', '', $sql) ?? $sql;
if (file_put_contents($importSql, $sql) === false) {
    fwrite(STDERR, "Failed to write prepared SQL: {$importSql}\n");
    exit(1);
}

$mysqlBin = 'c:/xampp/mysql/bin/mysql.exe';
if (! is_file($mysqlBin)) {
    fwrite(STDERR, "mysql client not found: {$mysqlBin}\n");
    exit(1);
}

$cmd = sprintf(
    '%s -u %s -p%s %s < %s',
    escapeshellarg($mysqlBin),
    escapeshellarg($user),
    escapeshellarg($pass),
    escapeshellarg($database),
    escapeshellarg($importSql)
);

echo "Running mysql import via CLI...\n";
exec('cmd /c ' . $cmd, $output, $code);
if ($code !== 0) {
    fwrite(STDERR, "Import failed (exit {$code}).\n");
    if ($output !== []) {
        fwrite(STDERR, implode("\n", $output) . "\n");
    }
    exit(1);
}

echo "Resetting all user passwords to \"{$newPassword}\"...\n";
$stmt = $mysqli->prepare(
    'UPDATE users SET password = ?, must_change_password = 0, remember_token = NULL'
);
if (! $stmt) {
    fwrite(STDERR, 'Prepare failed: ' . $mysqli->error . "\n");
    exit(1);
}
$stmt->bind_param('s', $hash);
$stmt->execute();
$affected = $stmt->affected_rows;
$stmt->close();

$countResult = $mysqli->query('SELECT COUNT(*) AS c FROM users');
$row = $countResult ? $countResult->fetch_assoc() : ['c' => 0];
$userCount = (int) ($row['c'] ?? 0);

echo "Done. Users in database: {$userCount}. Password rows updated: {$affected}.\n";
echo "Bcrypt hash used: {$hash}\n";

$mysqli->close();
