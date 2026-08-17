@extends('layouts.app')

@section('title', 'My QR Code')
@section('page-title', 'My QR Code')
@section('page-subtitle', 'Present this code at a company attendance station')

@section('content')
<div class="max-w-xl mx-auto card p-8 text-center" x-data="qrCard('{{ $plain }}', '{{ $employee->fullName() }}')">
    <div class="text-sm text-slate-500">Employee</div>
    <h2 class="text-2xl font-extrabold">{{ $employee->fullName() }}</h2>
    <div class="mt-1 text-sm text-slate-500">Employee Number</div>
    <div class="font-semibold">{{ $employee->employee_number }}</div>
    <canvas x-ref="canvas" class="mx-auto mt-6 h-64 w-64 bg-white p-3 rounded-2xl border"></canvas>
    <p class="mt-4 text-sm text-slate-600">Present this QR code to the Attendance Station scanner.</p>
    <div class="mt-6 flex flex-wrap justify-center gap-2 print:hidden">
        <button type="button" class="btn-primary" @click="download">Download QR</button>
        <a class="btn-secondary" href="{{ route('employee.qr.print') }}" target="_blank">Print QR</a>
    </div>
</div>
@endsection
