@extends('layouts.guest')

@section('title', 'Attendance Station')

@section('content')
<div class="relative flex min-h-screen items-center justify-center overflow-hidden bg-gradient-to-br from-shell-950 via-shell-900 to-shell-800 p-6">
    <div class="pointer-events-none absolute -top-32 left-1/2 h-96 w-96 -translate-x-1/2 rounded-full bg-brand-600/15 blur-3xl"></div>
    <div class="pointer-events-none absolute -bottom-24 right-0 h-72 w-72 rounded-full bg-gold-500/10 blur-3xl"></div>

    <div class="relative w-full max-w-md">
        <div class="mb-8 text-center">
            <div class="brand-mark mx-auto h-14 w-14 rounded-2xl text-xl">B</div>
            <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-white">ATTENDANCE STATION</h1>
            <p class="mt-2 text-sm text-brand-200/70">BACS Construction and Development Corporation</p>
        </div>

        @if (session('device_conflict') || $errors->has('device'))
            <div class="mb-6 rounded-2xl border border-warn-400/40 bg-warn-400/10 p-6 text-center backdrop-blur">
                <div class="mx-auto mb-3 flex h-10 w-10 items-center justify-center rounded-xl bg-warn-400/20 text-warn-200">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                </div>
                <h2 class="text-xl font-extrabold text-warn-200">Station Already Registered</h2>
                <p class="mt-2 text-sm text-warn-100/90">This attendance station is already registered to another device.</p>
                <p class="mt-1 text-sm text-warn-100/70">Please contact the Super Admin to reset or transfer the station.</p>
            </div>
        @endif

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-brand-400/30 bg-brand-500/10 px-4 py-3 text-sm text-brand-200 backdrop-blur">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl border border-critical-400/30 bg-critical-500/10 px-4 py-3 text-sm text-critical-200 backdrop-blur">{{ session('error') }}</div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-surface p-8 text-ink shadow-float">
            <form method="POST" action="{{ route('station.login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label" for="station_id">Station ID</label>
                    <input id="station_id" class="input @error('station_id') input-error @enderror" name="station_id" value="{{ old('station_id') }}" required autofocus autocomplete="username" placeholder="BACS-STATION-001">
                </div>
                <div>
                    <label class="label" for="station_password">Station Password</label>
                    <input id="station_password" class="input" type="password" name="password" required autocomplete="current-password">
                </div>
                @error('station_id')
                    <p class="error-text">{{ $message }}</p>
                @enderror
                <button type="submit" class="btn-primary btn-block btn-lg">Login to Station</button>
            </form>
            <p class="mt-6 border-t border-line pt-5 text-center text-xs leading-relaxed text-muted">Do not use private or incognito browsing. Install this page as an app on the dedicated station device. Device binding is permanent until the Super Admin resets it.</p>
        </div>
    </div>
</div>
@endsection
