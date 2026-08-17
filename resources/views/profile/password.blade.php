@extends('layouts.app')

@section('title', 'Change Password')
@section('page-title', 'Change Password')

@section('content')
<div class="card p-6 max-w-lg">
    @if (auth()->user()->must_change_password)
        <div class="mb-4 rounded-xl bg-amber-50 border border-amber-100 px-4 py-3 text-sm text-amber-800">
            You are using a temporary password. Please set a new password to continue.
        </div>
    @endif
    <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-4">
        @csrf @method('PUT')
        <div><label class="label">Current password</label><input class="input" type="password" name="current_password" required></div>
        <div><label class="label">New password</label><input class="input" type="password" name="password" required></div>
        <div><label class="label">Confirm new password</label><input class="input" type="password" name="password_confirmation" required></div>
        <button class="btn-primary">Update password</button>
    </form>
</div>
@endsection
