@extends('layouts.app')

@section('title', 'Edit Super Admin')
@section('page-title', 'Edit Super Admin')
@section('page-subtitle', 'Update login credentials and account status')

@section('content')
<div class="max-w-2xl">
    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">{{ $admin->name }}</h2>
            <span class="chip">{{ $admin->username }}</span>
        </div>
        <form method="POST" action="{{ route('admin.settings.admins.update', $admin) }}" class="space-y-4 p-5">
            @csrf
            @method('PUT')

            <div>
                <label class="label" for="admin-name">Display name</label>
                <input id="admin-name" class="input @error('name') input-error @enderror" name="name" value="{{ old('name', $admin->name) }}" required>
                @error('name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="admin-email">Email</label>
                    <input id="admin-email" class="input @error('email') input-error @enderror" type="email" name="email" value="{{ old('email', $admin->email) }}" required>
                    @error('email')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="admin-username">Username</label>
                    <input id="admin-username" class="input @error('username') input-error @enderror" name="username" value="{{ old('username', $admin->username) }}" required autocomplete="username">
                    @error('username')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="admin-password">New password (optional)</label>
                    <input id="admin-password" class="input @error('password') input-error @enderror" type="password" name="password" minlength="8" autocomplete="new-password">
                    @error('password')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="admin-password-confirm">Confirm password</label>
                    <input id="admin-password-confirm" class="input" type="password" name="password_confirmation" minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div>
                <label class="label" for="admin-status">Account status</label>
                <select id="admin-status" class="select @error('status') input-error @enderror" name="status" required>
                    @foreach (\App\Enums\AccountStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $admin->status?->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                @error('status')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="btn-primary">Save changes</button>
                <a class="btn-outline" href="{{ route('admin.settings.index') }}">Back to settings</a>
            </div>
        </form>
    </div>
</div>
@endsection
