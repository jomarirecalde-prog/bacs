@extends('layouts.app')

@section('title', 'Change Password')
@section('page-title', 'Change Password')
@section('page-subtitle', 'Keep your account secure with a strong password')

@section('content')
<div class="max-w-lg space-y-4">
    @if (auth()->user()->must_change_password)
        <div class="alert-warning">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
            <span>You are using a temporary password. Please set a new password to continue.</span>
        </div>
    @endif

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">New Password</h2>
        </div>
        <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4 p-5">
            @csrf @method('PUT')
            <div>
                <label class="label" for="current_password">Current password</label>
                <input id="current_password" class="input @error('current_password') input-error @enderror" type="password" name="current_password" required autocomplete="current-password">
                @error('current_password')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" class="input @error('password') input-error @enderror" type="password" name="password" required autocomplete="new-password">
                @error('password')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" class="input" type="password" name="password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn-primary">Update password</button>
        </form>
    </div>
</div>
@endsection
