<?php

/**
 * XAMPP entry point: Laravel lives in /BACS/public.
 * Always redirect with an absolute path (never a relative "public/..." URL).
 */
$base = '/BACS/public';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

// Strip /BACS and optional /public prefix from the requested path.
$path = preg_replace('#^/BACS(/public)?#', '', $path);
$path = trim($path, '/');

$target = $base.($path !== '' ? '/'.$path : '/login');

header('Location: '.$target, true, 302);
exit;
