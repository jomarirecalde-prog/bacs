@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'Organization profile, super admin accounts, and holiday calendar')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card card-accent-brand h-fit overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Organization</h2>
        </div>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4 p-5">
            @csrf @method('PUT')
            <div>
                <label class="label" for="company_name">Company name</label>
                <input id="company_name" class="input @error('company_name') input-error @enderror" name="company_name" value="{{ $company }}" required>
                @error('company_name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="company_address">Company address</label>
                <input id="company_address" class="input" name="company_address" value="{{ $address }}">
            </div>
            <div class="alert-info">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs">Timezone is locked to Asia/Manila (Philippine Standard Time).</span>
            </div>
            <button type="submit" class="btn-primary">Save settings</button>
        </form>
    </div>

    <div class="card card-accent-gold overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Quick Holidays</h2>
            <span class="chip">{{ $holidays->count() }} configured</span>
        </div>

        <div class="border-b border-line px-5 pt-5">
            <div class="alert-info">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>
                    This is the simple single-day list. For multi-day holidays, meetings, announcements,
                    and audience targeting, use the
                    <a href="{{ route('admin.calendar.index') }}" class="font-semibold underline">Calendar &amp; Events</a> module.
                    Holidays from both places are honoured by attendance and the DTR.
                </span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.settings.holidays.store') }}" class="border-b border-line p-5">
            @csrf
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label class="label" for="holiday-name">Name</label>
                    <input id="holiday-name" class="input" name="name" placeholder="Holiday name" required>
                </div>
                <div>
                    <label class="label" for="holiday-date">Date</label>
                    <input id="holiday-date" class="input" type="date" name="holiday_date" required>
                </div>
                <div>
                    <label class="label" for="holiday-type">Type</label>
                    <select id="holiday-type" class="select" name="type">
                        <option value="regular">Regular</option>
                        <option value="special">Special</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="btn-gold mt-4">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add holiday
            </button>
        </form>

        <div class="p-5">
            @forelse ($holidays as $holiday)
                <div class="mb-2 flex items-center justify-between gap-3 rounded-xl border border-gold-200 bg-gold-50/60 px-4 py-2.5 last:mb-0">
                    <div class="min-w-0">
                        <div class="truncate text-sm font-bold text-ink">{{ $holiday->name }}</div>
                        <div class="text-xs text-muted">{{ $holiday->holiday_date->toFormattedDateString() }} · {{ ucfirst($holiday->type) }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.holidays.destroy', $holiday) }}" onsubmit="return confirm('Remove this holiday?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="btn-outline-danger btn-sm">Remove</button>
                    </form>
                </div>
            @empty
                <x-empty-state title="No holidays configured" message="Add regular and special non-working days so attendance is computed correctly." icon="calendar" />
            @endforelse
        </div>
    </div>
</div>

<div class="mt-6 card overflow-hidden">
    <div class="card-header">
        <h2 class="card-title">Super Admin Accounts</h2>
        <span class="chip">{{ $adminAccounts->count() }} account{{ $adminAccounts->count() === 1 ? '' : 's' }}</span>
    </div>
    <div class="border-b border-line px-5 pt-5">
        <div class="alert-info">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-xs">These accounts have full system access. Boss / management (supervisor) logins are managed through the Employees module.</span>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.settings.admins.store') }}" class="border-b border-line p-5">
        @csrf
        <p class="mb-4 text-sm font-semibold text-ink">Add super admin account</p>
        <div class="grid gap-3 lg:grid-cols-6">
            <div class="lg:col-span-2">
                <label class="label" for="new-admin-name">Display name</label>
                <input id="new-admin-name" class="input @error('name') input-error @enderror" name="name" value="{{ old('name') }}" placeholder="Jane Admin" required>
                @error('name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="new-admin-email">Email</label>
                <input id="new-admin-email" class="input @error('email') input-error @enderror" type="email" name="email" value="{{ old('email') }}" required>
                @error('email')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="new-admin-username">Username</label>
                <input id="new-admin-username" class="input @error('username') input-error @enderror" name="username" value="{{ old('username') }}" autocomplete="off" required>
                @error('username')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="new-admin-password">Password</label>
                <input id="new-admin-password" class="input @error('password') input-error @enderror" type="password" name="password" minlength="8" autocomplete="new-password" required>
                @error('password')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="new-admin-password-confirm">Confirm</label>
                <input id="new-admin-password-confirm" class="input" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
            </div>
        </div>
        <div class="mt-4 flex flex-wrap items-end gap-4">
            <div class="w-full sm:w-48">
                <label class="label" for="new-admin-status">Status</label>
                <select id="new-admin-status" class="select @error('status') input-error @enderror" name="status">
                    @foreach (\App\Enums\AccountStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', 'active') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                @error('status')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add super admin
            </button>
        </div>
    </form>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($adminAccounts as $account)
                    <tr>
                        <td>
                            <div class="font-semibold text-ink">{{ $account->name }}</div>
                            @if ($account->id === auth()->id())
                                <div class="text-xs text-muted">Signed in as you</div>
                            @endif
                        </td>
                        <td class="font-mono text-sm text-ink">{{ $account->username }}</td>
                        <td class="text-sm text-muted">{{ $account->email }}</td>
                        <td>
                            <span class="{{ $account->status?->value === 'active' ? 'badge-brand' : 'badge-neutral' }}">
                                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                {{ $account->status?->label() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="inline-flex flex-wrap justify-end gap-2">
                                <a class="btn-outline-info btn-sm" href="{{ route('admin.settings.admins.edit', $account) }}">Edit</a>
                                @if ($account->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.settings.admins.destroy', $account) }}" onsubmit="return confirm('Delete super admin account «{{ $account->username }}»? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-outline-danger btn-sm">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-0">
                            <x-empty-state title="No super admin accounts" message="Use the form above to add the first super admin login." icon="users" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
