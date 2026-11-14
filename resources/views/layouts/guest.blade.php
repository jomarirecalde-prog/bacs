<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#047857">
    <meta name="description" content="BACS Attendance Management System — sign in to manage attendance and daily time records.">
    <title>@yield('title', 'Sign in') · {{ config('app.name') }}</title>
    @include('partials.favicon')
    @include('partials.pwa-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas">
    @php
        $guestBackUrl = trim((string) $__env->yieldContent('back-url'));
        $guestBackLabel = trim((string) $__env->yieldContent('back-label'));
        $guestBackLabel = $guestBackLabel !== '' ? $guestBackLabel : 'Back';
        if ($guestBackUrl === '') {
            if (request()->routeIs('login', 'station.login')) {
                $guestBackUrl = route('landing');
                $guestBackLabel = 'Back to home';
            } else {
                $guestBackUrl = request()->routeIs('station.*') ? route('station.login') : route('login');
                $guestBackLabel = 'Back to sign in';
            }
        }
    @endphp
    <x-guest-back-button :url="$guestBackUrl" :label="$guestBackLabel" />
    @yield('content')
    @include('partials.pwa-ui')
</body>
</html>
