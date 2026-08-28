@extends('layouts.guest')

@section('title', 'Sign in')

@section('content')
<div class="grid min-h-screen lg:grid-cols-2">
    <div class="relative hidden flex-col justify-between overflow-hidden bg-gradient-to-br from-shell-900 via-shell-800 to-brand-800 p-12 text-white lg:flex">
        <div class="pointer-events-none absolute -right-24 -top-24 h-72 w-72 rounded-full bg-brand-500/15 blur-3xl"></div>
        <div class="pointer-events-none absolute -bottom-32 -left-16 h-80 w-80 rounded-full bg-gold-500/10 blur-3xl"></div>

        <div class="relative flex items-center gap-3">
            <div class="brand-mark h-11 w-11 rounded-2xl text-lg">B</div>
            <div>
                <div class="font-extrabold tracking-wide">BACS DTR</div>
                <div class="text-sm text-brand-200/80">Daily Time Record Monitoring</div>
            </div>
        </div>

        <div class="relative">
            <span class="badge-featured mb-6">Institutional Timekeeping</span>
            <h1 class="text-4xl font-extrabold leading-tight tracking-tight">Clean attendance.<br><span class="text-gold-300">Accurate DTR.</span></h1>
            <p class="mt-4 max-w-md text-brand-100/80">Employees clock in and out in Philippine Standard Time. Supervisors monitor attendance, correct records, and generate official DTR reports.</p>

            <div class="mt-8 grid max-w-md gap-3">
                @foreach ([
                    ['Server-authoritative timestamps', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
                    ['QR-based attendance stations', 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2z'],
                    ['Fully audited sign-in activity', 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ] as [$text, $path])
                    <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-white/5 px-4 py-3 backdrop-blur">
                        <svg class="h-5 w-5 shrink-0 text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $path }}"/></svg>
                        <span class="text-sm font-medium text-brand-50">{{ $text }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <p class="relative text-xs text-brand-200/50">Asia/Manila · Server-authoritative timestamps</p>
    </div>

    <div class="flex items-center justify-center bg-canvas p-6 sm:p-12">
        <div class="w-full max-w-md">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <div class="brand-mark h-11 w-11 rounded-2xl text-lg">B</div>
                <div>
                    <div class="text-lg font-extrabold text-ink">BACS DTR</div>
                    <div class="text-sm text-muted">Sign in to continue</div>
                </div>
            </div>

            <div class="card card-accent-brand p-8">
                <h2 class="text-2xl font-extrabold tracking-tight text-ink">Welcome back</h2>
                <p class="mt-1 text-sm text-muted">Use your employee number, username, or email.</p>

                <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-4">
                    @csrf
                    <div>
                        <label class="label" for="login">Username, employee number, or email</label>
                        <input id="login" class="input @error('login') input-error @enderror" name="login" value="{{ old('login') }}" required autofocus autocomplete="username">
                    </div>
                    <div>
                        <label class="label" for="password">Password</label>
                        <input id="password" class="input" type="password" name="password" required autocomplete="current-password">
                    </div>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-ink-soft">
                        <input type="checkbox" name="remember" class="checkbox"> Remember me
                    </label>
                    @error('login')
                        <p class="alert-danger">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-critical-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                    <button type="submit" class="btn-primary btn-block btn-lg">Sign in</button>
                </form>
            </div>

            <p class="mt-6 text-center text-xs text-muted">Authorized personnel only. All sign-ins are audited.</p>
            <p class="mt-3 text-center">
                <a class="link inline-flex items-center gap-1.5 text-xs" href="{{ url('/attendance-station/login') }}">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                    Attendance Station login
                </a>
            </p>
        </div>
    </div>
</div>
@endsection
