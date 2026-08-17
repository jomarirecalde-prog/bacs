<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR Code · {{ $employee->fullName() }}</title>
    @vite(['resources/css/app.css', 'resources/js/qrcode-page.js'])
    <style>
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="min-h-screen bg-white text-slate-900">
    <div class="mx-auto max-w-md p-8 text-center" x-data="qrCard('{{ $plain }}', '{{ $employee->fullName() }}')">
        <div class="text-xs font-semibold uppercase tracking-widest text-slate-500">BACS Construction</div>
        <h1 class="mt-2 text-2xl font-extrabold">{{ $employee->fullName() }}</h1>
        <p class="text-sm text-slate-500">{{ $employee->employee_number }}</p>
        <p class="text-sm text-slate-500">{{ $employee->department?->name }} · {{ $employee->position }}</p>
        <canvas x-ref="canvas" class="mx-auto mt-6 h-72 w-72"></canvas>
        <p class="mt-4 text-sm">Present this QR code to the Attendance Station scanner.</p>
        <div class="mt-6 flex justify-center gap-2 no-print">
            <button class="btn-primary" type="button" onclick="window.print()">Print QR</button>
            <button class="btn-secondary" type="button" @click="download">Download QR</button>
        </div>
    </div>
</body>
</html>
