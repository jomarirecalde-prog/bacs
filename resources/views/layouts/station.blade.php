<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#047857">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BACS Station">
    <link rel="manifest" href="{{ asset('station.webmanifest') }}">
    @include('partials.favicon')
    <title>@yield('title', 'Attendance Station') · BACS DTR</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/station.js'])
</head>
<body class="min-h-screen bg-shell-950 text-white antialiased">
    @unless (request()->routeIs('station.dashboard'))
        @php
            $stationBackUrl = trim((string) $__env->yieldContent('back-url'));
            $stationBackUrl = $stationBackUrl !== '' ? $stationBackUrl : route('station.dashboard');
            $stationBackLabel = trim((string) $__env->yieldContent('back-label'));
            $stationBackLabel = $stationBackLabel !== '' ? $stationBackLabel : 'Back';
        @endphp
        <div class="safe-top px-4 pt-4 sm:px-5">
            <a href="{{ $stationBackUrl }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-brand-200/70 transition hover:text-white">
                <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
                {{ $stationBackLabel === 'Back' ? 'Back to scanner' : $stationBackLabel }}
            </a>
        </div>
    @endunless
    @yield('content')
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('{{ asset('sw-station.js') }}').catch(() => {});
        }
    </script>
</body>
</html>
