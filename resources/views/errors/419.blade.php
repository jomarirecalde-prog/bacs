@extends('layouts.guest')

@section('title', 'Session Expired')

@section('content')
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-md rounded-2xl border border-line bg-surface p-8 text-center shadow-card">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-warn-50 text-warn-600">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
        </div>
        <h1 class="text-xl font-extrabold text-ink">Your BACS session has expired</h1>
        <p class="mt-2 text-sm text-muted">
            For your security, your session ended after a period of inactivity.
            Please sign in again to continue.
        </p>
        <a href="{{ route('login') }}" class="btn-primary mt-6 inline-flex w-full justify-center">
            Sign In Again
        </a>
    </div>
</div>
@endsection
