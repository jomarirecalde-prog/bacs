@extends('layouts.station')

@section('title', 'Station Settings')

@section('content')
<div class="mx-auto min-h-screen max-w-xl p-6">
    <a href="{{ route('station.dashboard') }}" class="text-sm font-semibold text-slate-400 hover:text-white">← Back to scanner</a>
    <h1 class="mt-6 text-3xl font-extrabold">Station Settings</h1>
    <div class="mt-6 space-y-3 rounded-3xl border border-white/10 bg-slate-900 p-6 text-sm">
        <div class="flex justify-between gap-4"><span class="text-slate-400">Station ID</span><span class="font-semibold">{{ $station->station_code }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-400">Name</span><span class="text-right">{{ $station->station_name }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-400">Location</span><span class="text-right">{{ $station->location }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-400">Status</span><span>{{ $station->status->label() }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-400">Device</span><span>{{ $station->device_status->label() }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-400">Idle timeout</span><span>{{ $station->idleTimeoutLabel() }}</span></div>
    </div>
    <p class="mt-4 text-xs text-slate-400">Logging out does not remove device binding. Only the Super Admin can reset or transfer this station to another device.</p>
    <p class="mt-2 text-xs text-slate-400">Install this page on the home screen for a dedicated kiosk. Attendance is always recorded by the server — offline scans are not stored.</p>
    <form method="POST" action="{{ route('station.logout') }}" class="mt-8">
        @csrf
        <button class="btn-danger w-full py-3">Logout Station</button>
    </form>
</div>
@endsection
