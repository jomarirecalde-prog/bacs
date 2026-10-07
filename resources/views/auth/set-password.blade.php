@extends('layouts.guest')

@section('title', 'Set your password')

@section('content')
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="card w-full max-w-md">
        <div class="card-header">
            <h1 class="card-title">Set your BACS password</h1>
        </div>
        <form method="POST" action="{{ $submitUrl }}" class="card-body space-y-4">
            @csrf
            <p class="text-sm text-muted">
                Hello {{ $user->name }}, choose a secure password for username <strong>{{ $user->username }}</strong>.
                This link expires after a few days.
            </p>
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" type="password" name="password" class="input" required autocomplete="new-password">
                @error('password') <p class="error-text">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm password</label>
                <input id="password_confirmation" type="password" name="password_confirmation" class="input" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn-primary w-full">Save password and continue</button>
        </form>
    </div>
</div>
@endsection
