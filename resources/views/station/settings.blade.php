@extends('layouts.station')

@section('title', 'Station Settings')

@section('content')
<div class="min-h-screen bg-gradient-to-b from-shell-950 to-shell-900">
    <div class="mx-auto max-w-xl p-6">
        <h1 class="text-3xl font-extrabold text-white">Station Settings</h1>
        <p class="mt-1 text-sm text-brand-200/70">Read-only device and station information.</p>

        <div class="shell-panel mt-6 divide-y divide-white/10 text-sm">
            @foreach ([
                ['Station ID', $station->station_code],
                ['Name', $station->station_name],
                ['Location', $station->location],
                ['Status', $station->status->label()],
                ['Device', $station->device_status->label()],
                ['Idle timeout', $station->idleTimeoutLabel()],
            ] as [$term, $definition])
                <div class="flex justify-between gap-4 px-6 py-3.5">
                    <span class="text-brand-200/60">{{ $term }}</span>
                    <span class="text-right font-semibold text-white">{{ $definition }}</span>
                </div>
            @endforeach
        </div>

        <div class="mt-6 rounded-xl border border-gold-400/30 bg-gold-500/10 px-4 py-3 text-xs leading-relaxed text-gold-100/90">
            Logging out does not remove device binding. Only the Super Admin can reset or transfer this station to another device.
        </div>
        <p class="mt-3 text-xs leading-relaxed text-brand-200/60">Install this page on the home screen for a dedicated kiosk. Attendance is always recorded by the server — offline scans are not stored.</p>

        <form method="POST" action="{{ route('station.logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="btn-danger btn-block btn-lg">Logout Station</button>
        </form>
    </div>
</div>
@endsection
