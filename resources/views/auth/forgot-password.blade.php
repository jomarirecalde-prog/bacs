@extends('layouts.landing')

@section('title', 'Forgot password')

@section('content')
<div class="flex min-h-screen flex-col">
    <header class="landing-nav border-b border-white/5">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex min-w-0 items-center gap-3">
                <x-bacs-logo class="h-10 w-auto shrink-0" />
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-extrabold text-brand-50 sm:text-base">{{ config('app.name') }}</div>
                    <p class="truncate text-xs text-brand-200/65">{{ $companyName }}</p>
                </div>
            </a>
            <a href="{{ route('login') }}" class="landing-link text-sm">Back to sign in</a>
        </div>
    </header>

    <main class="flex flex-1 items-center justify-center px-4 py-12 sm:px-6">
        <div class="landing-auth-card w-full max-w-md">
            @php
                $resetSent = session('reset_status') === 'sent' && session('reset_message');
            @endphp

            <h1 class="text-2xl font-extrabold tracking-tight text-brand-50">Forgot password?</h1>
            @if ($resetSent)
                <p class="mt-1 text-sm text-brand-200/75">Check the email below for your secure reset link.</p>
            @else
                <p class="mt-1 text-sm text-brand-200/75">
                    Enter your employee ID, username, or email. If your account has a registered email, we will send a secure reset link.
                </p>
            @endif

            @if (session('reset_status') && session('reset_message'))
                @php
                    $resetAlertClass = match (session('reset_status')) {
                        'sent' => 'landing-alert-success',
                        default => 'landing-alert-error',
                    };
                @endphp
                <div class="{{ $resetAlertClass }} mt-5" role="alert" aria-live="polite">
                    <div class="flex items-start gap-3">
                        @if (session('reset_status') === 'sent')
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        @else
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-critical-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                        @endif
                        <div>
                            <p class="font-semibold text-brand-50">
                                @if (session('reset_status') === 'sent')
                                    Reset link sent
                                @else
                                    Reset link not sent
                                @endif
                            </p>
                            <p class="mt-1 text-sm leading-relaxed opacity-95">{{ session('reset_message') }}</p>
                        </div>
                    </div>
                </div>
            @endif

            @if ($resetSent)
                <div class="mt-7 flex flex-col gap-3 sm:flex-row">
                    <a href="{{ route('login') }}" class="btn-primary btn-block btn-lg">Back to sign in</a>
                    <a href="{{ route('password.request') }}" class="landing-btn-outline btn-block text-center">Send again</a>
                </div>
            @else
                <form method="POST" action="{{ route('password.email') }}" class="mt-7 space-y-4" novalidate>
                    @csrf
                    <div>
                        <label class="landing-label" for="login">Username, employee ID, or email</label>
                        <div class="landing-input-wrap">
                            <svg class="landing-input-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            <input
                                id="login"
                                class="landing-input @error('login') landing-input-error @enderror"
                                name="login"
                                value="{{ $prefillLogin }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Employee number, username, or email"
                            >
                        </div>
                        @error('login')
                            <p class="mt-2 text-xs text-critical-400">{{ $message }}</p>
                        @enderror
                    </div>
                    <button type="submit" class="btn-primary btn-block btn-lg">Send reset link</button>
                </form>

                <p class="mt-5 text-center text-xs text-brand-200/60">
                    No email on file?
                    <a href="{{ route('landing') }}#contact" class="landing-link">Contact HR or the DPO</a>
                    for help.
                </p>
            @endif
        </div>
    </main>
</div>
@endsection
