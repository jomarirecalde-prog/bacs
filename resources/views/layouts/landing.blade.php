<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#047857">
    <meta name="description" content="{{ config('app.name') }} — employee attendance, daily time records, and workforce administration.">
    <title>@yield('title', 'Home') · {{ config('app.name') }}</title>
    @include('partials.favicon')
    @include('partials.pwa-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/landing.js'])
</head>
<body class="landing-page min-h-screen bg-shell-950 text-brand-50 antialiased" data-full-page-nav="1">
    @yield('content')
    @include('partials.pwa-ui')
</body>
</html>
