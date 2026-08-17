@extends('layouts.app')

@section('title', 'Employee QR')
@section('page-title', 'Employee QR Code')
@section('page-subtitle', $employee->fullName().' · '.$employee->employee_number)

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-8 text-center" x-data="qrCard('{{ $plain }}', '{{ $employee->fullName() }}')">
        <canvas x-ref="canvas" class="mx-auto h-64 w-64 bg-white p-3 rounded-2xl border"></canvas>
        <p class="mt-4 text-sm text-slate-500">Opaque attendance token. No password or personal data is encoded.</p>
        <div class="mt-6 flex flex-wrap justify-center gap-2">
            <button type="button" class="btn-primary" @click="download">Download QR</button>
            <a class="btn-secondary" href="{{ route('admin.employees.qr.print', $employee) }}" target="_blank">Print QR</a>
        </div>
        <p class="mt-3 text-xs text-slate-500">Status: {{ $token->status->label() }} · Generated {{ $token->generated_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</p>
    </div>
    <div class="card p-6 space-y-3">
        <h3 class="font-bold">QR Management</h3>
        <p class="text-sm text-slate-500">Regenerating immediately invalidates the previous QR code.</p>
        <form method="POST" action="{{ route('admin.employees.qr.regenerate', $employee) }}" onsubmit="return confirm('Regenerate this QR code? The current code will stop working immediately.')">
            @csrf
            <button class="btn-primary">Regenerate QR Code</button>
        </form>
        @if ($token->isActive())
            <form method="POST" action="{{ route('admin.employees.qr.disable', $employee) }}">
                @csrf
                <button class="btn-danger">Disable QR</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.employees.qr.enable', $employee) }}">
                @csrf
                <button class="btn-primary">Enable QR</button>
            </form>
        @endif
        <a class="btn-secondary inline-flex" href="{{ route('admin.employees.show', $employee) }}">Back to employee</a>
    </div>
</div>
@endsection
