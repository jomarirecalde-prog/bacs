@extends('layouts.guest')

@section('title', 'Reset password')

@section('content')
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="card w-full max-w-md">
        <div class="card-header">
            <h1 class="card-title">Reset your password</h1>
        </div>
        <form method="POST" action="{{ $submitUrl }}" class="card-body space-y-4">
            @csrf
            <p class="text-sm text-muted">
                Hello {{ $user->name }}, choose a new password for username <strong>{{ $user->username }}</strong>.
                This link expires after about one hour.
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
            <button type="submit" class="btn-primary w-full">Save new password</button>
        </form>
        <p class="card-footer text-center text-xs text-muted">
            <a class="link" href="{{ route('login') }}">Return to sign in</a>
        </p>
    </div>
</div>
@endsection
