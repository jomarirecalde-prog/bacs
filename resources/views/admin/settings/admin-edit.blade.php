@extends('layouts.app')

@section('title', 'Edit Super Admin')
@section('page-title', 'Edit Super Admin')
@section('page-subtitle', 'Update login credentials and account status')

@section('content')
<div class="mx-auto max-w-2xl">
    <div class="mb-4">
        <a href="{{ route('admin.settings.index') }}#settings-admins" class="btn-outline btn-sm">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to settings
        </a>
    </div>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <div class="min-w-0">
                <h2 class="card-title text-base">{{ $admin->name }}</h2>
                <p class="mt-0.5 truncate font-mono text-sm text-muted">{{ $admin->username }}</p>
            </div>
            <span class="{{ $admin->status?->value === 'active' ? 'badge-brand' : 'badge-neutral' }} shrink-0">
                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                {{ $admin->status?->label() }}
            </span>
        </div>
        <form method="POST" action="{{ route('admin.settings.admins.update', $admin) }}" class="form-card-body">
            @csrf
            @method('PUT')

            <div>
                <label class="label" for="admin-name">Display name</label>
                <input id="admin-name" class="input @error('name') input-error @enderror" name="name" value="{{ old('name', $admin->name) }}" required>
                @error('name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div class="form-grid">
                <div>
                    <label class="label" for="admin-email">Email</label>
                    <input id="admin-email" class="input @error('email') input-error @enderror" type="email" name="email" value="{{ old('email', $admin->email) }}" inputmode="email" required>
                    @error('email')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="admin-username">Username</label>
                    <input id="admin-username" class="input @error('username') input-error @enderror" name="username" value="{{ old('username', $admin->username) }}" required autocomplete="username">
                    @error('username')<p class="error-text">{{ $message }}</p>@enderror
                </div>
            </div>
            <div class="form-grid">
                <div>
                    <label class="label" for="admin-password">New password <span class="font-normal normal-case text-muted">(optional)</span></label>
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

            <div class="action-group-stacked border-t border-line pt-4">
                <button type="submit" class="btn-primary w-full sm:w-auto">Save changes</button>
                <a class="btn-outline w-full sm:w-auto" href="{{ route('admin.settings.index') }}#settings-admins">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
