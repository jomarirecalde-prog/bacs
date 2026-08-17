@extends('layouts.guest')

@section('title', 'Attendance Station')

@section('content')
<div class="flex min-h-screen items-center justify-center bg-slate-950 p-6">
    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-xl font-extrabold text-white">B</div>
            <h1 class="mt-4 text-3xl font-extrabold tracking-tight text-white">ATTENDANCE STATION</h1>
            <p class="mt-2 text-sm text-slate-400">BACS Construction and Development Corporation</p>
        </div>

        @if (session('device_conflict') || $errors->has('device'))
            <div class="mb-6 rounded-2xl border border-amber-400/30 bg-amber-500/10 p-6 text-center">
                <h2 class="text-xl font-extrabold text-amber-200">Station Already Registered</h2>
                <p class="mt-2 text-sm text-amber-100/90">This attendance station is already registered to another device.</p>
                <p class="mt-2 text-sm text-amber-100/80">Please contact the Super Admin to reset or transfer the station.</p>
            </div>
        @endif

        @if (session('success'))
            <div class="mb-4 rounded-xl bg-emerald-500/10 border border-emerald-400/20 px-4 py-3 text-sm text-emerald-200">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-xl bg-red-500/10 border border-red-400/20 px-4 py-3 text-sm text-red-200">{{ session('error') }}</div>
        @endif

        <div class="rounded-3xl border border-white/10 bg-white p-8 text-slate-900 shadow-2xl">
            <form method="POST" action="{{ route('station.login.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="label">Station ID</label>
                    <input class="input" name="station_id" value="{{ old('station_id') }}" required autofocus autocomplete="username" placeholder="BACS-STATION-001">
                </div>
                <div>
                    <label class="label">Station Password</label>
                    <input class="input" type="password" name="password" required autocomplete="current-password">
                </div>
                @error('station_id')
                    <p class="text-sm text-red-600">{{ $message }}</p>
                @enderror
                <button class="btn-primary w-full py-3 text-base">Login to Station</button>
            </form>
            <p class="mt-6 text-center text-xs text-slate-500">Do not use private or incognito browsing. Install this page as an app on the dedicated station device. Device binding is permanent until the Super Admin resets it.</p>
        </div>
    </div>
</div>
@endsection
