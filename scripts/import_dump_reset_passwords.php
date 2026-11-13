<?php

declare(strict_types=1);

$host = '127.0.0.1';
$user = 'root';
$pass = 'password';
$database = 'bacs_db';
$sqlFile = __DIR__ . '/../docs/u899628465_bacs (2).sql';
$newPassword = '12345678';

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

if (! $mysqli->multi_query($sql)) {
    fwrite(STDERR, 'Import failed: ' . $mysqli->error . "\n");
    exit(1);
}

do {
    if ($result = $mysqli->store_result()) {
        $result->free();
    }
} while ($mysqli->more_results() && $mysqli->next_result());

if ($mysqli->errno) {
    fwrite(STDERR, 'Import error after queries: ' . $mysqli->error . "\n");
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
