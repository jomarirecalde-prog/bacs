<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#047857">
    <title>QR Code · {{ $employee->fullName() }}</title>
    <link rel="icon" href="{{ asset('station-icon.svg') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/qrcode-page.js'])
</head>
<body class="min-h-screen bg-canvas text-ink print:bg-white">
    <div class="mx-auto max-w-md p-8" x-data="qrCard('{{ $plain }}', '{{ $employee->fullName() }}')">
        <div class="card-featured p-8 text-center print:border-0 print:shadow-none">
            <div class="text-xs font-bold uppercase tracking-[0.2em] text-gold-700">BACS Construction</div>
            <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-ink">{{ $employee->fullName() }}</h1>
            <p class="mt-1 text-sm font-semibold text-muted tabular-nums">{{ $employee->employee_number }}</p>
            <p class="text-sm text-muted">{{ $employee->department?->name }} · {{ $employee->position }}</p>
            <canvas x-ref="canvas" class="mx-auto mt-6 h-72 w-72"></canvas>
            <p class="mt-4 text-sm text-ink-soft">Present this QR code to the Attendance Station scanner.</p>
        </div>

        <div class="mt-6 flex justify-center gap-2 no-print">
            <button class="btn-primary" type="button" onclick="window.print()">Print QR</button>
            <button class="btn-secondary" type="button" @click="download">Download QR</button>
        </div>
    </div>
</body>
</html>
