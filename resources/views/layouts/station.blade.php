<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="BACS Station">
    <link rel="manifest" href="{{ asset('station.webmanifest') }}">
    <link rel="icon" href="{{ asset('station-icon.svg') }}">
    <title>@yield('title', 'Attendance Station') · BACS DTR</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/station.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white antialiased">
    @yield('content')
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('{{ asset('sw-station.js') }}').catch(() => {});
        }
    </script>
    <style>[x-cloak]{display:none !important}</style>
</body>
</html>
