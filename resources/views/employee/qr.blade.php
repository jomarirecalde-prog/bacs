@extends('layouts.app')

@section('title', 'My QR Code')
@section('page-title', 'My QR Code')
@section('page-subtitle', 'Present this code at a company attendance station')

@section('content')
<div class="mx-auto max-w-xl card-featured p-8 text-center" x-data="qrCard('{{ $plain }}', '{{ $employee->fullName() }}')">
    <span class="badge-featured">Attendance Credential</span>

    <div class="mt-5">
        <div class="text-xs font-bold uppercase tracking-wide text-muted">Employee</div>
        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-ink">{{ $employee->fullName() }}</h2>
        <div class="mt-3 text-xs font-bold uppercase tracking-wide text-muted">Employee Number</div>
        <div class="font-bold text-ink tabular-nums">{{ $employee->employee_number }}</div>
    </div>

    <canvas x-ref="canvas" class="mx-auto mt-6 h-64 w-64 rounded-2xl border border-gold-200 bg-white p-3 shadow-soft"></canvas>

    <p class="mt-4 text-sm text-ink-soft">Present this QR code to the Attendance Station scanner.</p>

    <div class="mt-6 flex flex-wrap justify-center gap-2 no-print">
        <button type="button" class="btn-primary" @click="download">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Download QR
        </button>
        <a class="btn-secondary" href="{{ route('employee.qr.print') }}" target="_blank">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print QR
        </a>
    </div>
</div>
@endsection
