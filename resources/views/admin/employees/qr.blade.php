@extends('layouts.app')

@section('title', 'Employee QR')
@section('page-title', 'Employee QR Code')
@section('page-subtitle', $employee->fullName().' · '.$employee->employee_number)

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card-featured p-8 text-center" x-data="qrCard('{{ $plain }}', '{{ $employee->fullName() }}')">
        <span class="badge-featured">Attendance Credential</span>
        <canvas x-ref="canvas" class="mx-auto mt-5 h-64 w-64 rounded-2xl border border-gold-200 bg-white p-3 shadow-soft"></canvas>
        <p class="mt-4 text-sm text-muted">Opaque attendance token. No password or personal data is encoded.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <button type="button" class="btn-primary" @click="download">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                Download QR
            </button>
            <a class="btn-secondary" href="{{ route('admin.employees.qr.print', $employee) }}" target="_blank">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
                Print QR
            </a>
        </div>
        <p class="mt-4 text-xs text-muted">
            Status: <span class="font-bold {{ $token->isActive() ? 'text-brand-700' : 'text-warn-700' }}">{{ $token->status->label() }}</span>
            · Generated {{ $token->generated_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}
        </p>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h3 class="card-title">QR Management</h3>
        </div>
        <div class="space-y-4 p-5">
            <div class="alert-warning">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                <span>Regenerating immediately invalidates the previous QR code. The employee must use the new code at the station.</span>
            </div>

            <form method="POST" action="{{ route('admin.employees.qr.regenerate', $employee) }}" onsubmit="return confirm('Regenerate this QR code? The current code will stop working immediately.')">
                @csrf
                <button type="submit" class="btn-warning btn-block">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    Regenerate QR Code
                </button>
            </form>

            @if ($token->isActive())
                <form method="POST" action="{{ route('admin.employees.qr.disable', $employee) }}">
                    @csrf
                    <button type="submit" class="btn-outline-danger btn-block">Disable QR</button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.employees.qr.enable', $employee) }}">
                    @csrf
                    <button type="submit" class="btn-primary btn-block">Enable QR</button>
                </form>
            @endif

            <a class="btn-outline btn-block" href="{{ route('admin.employees.show', $employee) }}">Back to employee</a>
        </div>
    </div>
</div>
@endsection
