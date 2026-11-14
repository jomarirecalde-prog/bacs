<?php

declare(strict_types=1);

$newPassword = 'password';
$hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
$mysqli = new mysqli('127.0.0.1', 'root', 'password', 'bacs_db');
$stmt = $mysqli->prepare('UPDATE users SET password = ?, must_change_password = 0, remember_token = NULL');
$stmt->bind_param('s', $hash);
$stmt->execute();
echo "Updated {$stmt->affected_rows} users to password: {$newPassword}\n";
