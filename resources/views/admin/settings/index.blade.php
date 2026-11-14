@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')
@section('page-subtitle', 'Organization profile, email, holidays, admin accounts, and approval workflows')

@section('content')
@php
    $upcomingHolidays = $holidays->filter(fn ($h) => $h->holiday_date->isToday() || $h->holiday_date->isFuture())->count();
    $adminFormOpen = $errors->hasAny(['name', 'email', 'username', 'password', 'status']);
@endphp

<div class="mx-auto max-w-6xl space-y-6">
    {{-- Quick links & section jump --}}
    <div class="flex flex-col gap-4">
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.calendar.index') }}" class="btn-outline btn-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                Calendar &amp; events
            </a>
            <a href="{{ route('admin.payroll.settings.index') }}" class="btn-outline btn-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/></svg>
                Payroll configuration
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card p-4">
                <div class="stat-label">Company</div>
                <div class="mt-1 truncate text-sm font-bold text-ink" title="{{ $company }}">{{ $company }}</div>
            </div>
            <div class="card p-4">
                <div class="stat-label">Holidays</div>
                <div class="stat-value text-xl sm:text-2xl">{{ $holidays->count() }}</div>
                <div class="stat-hint">{{ $upcomingHolidays }} upcoming</div>
            </div>
            <div class="card p-4">
                <div class="stat-label">Super admins</div>
                <div class="stat-value text-xl sm:text-2xl">{{ $adminAccounts->count() }}</div>
            </div>
            <div class="card p-4">
                <div class="stat-label">Dept. workflows</div>
                <div class="stat-value text-xl sm:text-2xl">{{ $approvalDepartments->count() }}</div>
            </div>
        </div>

        <nav class="sticky top-16 z-20 -mx-3 border-y border-line bg-surface/95 px-3 py-2.5 backdrop-blur-md sm:-mx-4 sm:px-4 lg:-mx-8 lg:px-8" aria-label="Settings sections">
            <div class="pill-tabs w-full min-w-0">
                <a href="#settings-organization" class="pill-tab shrink-0 whitespace-nowrap">Organization</a>
                <a href="#settings-smtp" class="pill-tab shrink-0 whitespace-nowrap">Email</a>
                <a href="#settings-holidays" class="pill-tab shrink-0 whitespace-nowrap">Holidays</a>
                <a href="#settings-admins" class="pill-tab shrink-0 whitespace-nowrap">Super admins</a>
                <a href="#approval-workflow" class="pill-tab shrink-0 whitespace-nowrap">Approvals</a>
                <a href="#pardon-approval-workflow" class="pill-tab shrink-0 whitespace-nowrap">Pardon</a>
            </div>
        </nav>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <section id="settings-organization" class="card card-accent-brand h-fit scroll-mt-28 overflow-hidden">
            <div class="card-header">
                <div class="min-w-0">
                    <h2 class="card-title text-base">Organization</h2>
                    <p class="mt-0.5 text-sm text-muted">Company name and address appear on reports and exports.</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.settings.update') }}" class="form-card-body">
                @csrf @method('PUT')
                <div>
                    <label class="label" for="company_name">Company name</label>
                    <input id="company_name" class="input @error('company_name') input-error @enderror" name="company_name" value="{{ $company }}" required autocomplete="organization">
                    @error('company_name')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="company_address">Company address</label>
                    <textarea id="company_address" class="textarea" name="company_address" rows="2" placeholder="Street, city, province">{{ $address }}</textarea>
                </div>
                <div class="alert-info">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-xs">Timezone is locked to <strong class="font-semibold">Asia/Manila</strong> (Philippine Standard Time).</span>
                </div>
                <button type="submit" class="btn-primary w-full sm:w-auto">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Save organization
                </button>
            </form>
        </section>

        <section id="settings-holidays" class="card card-accent-gold scroll-mt-28 overflow-hidden" x-data="{ filter: '' }">
            <div class="card-header">
                <div class="min-w-0">
                    <h2 class="card-title text-base">Quick holidays</h2>
                    <p class="mt-0.5 text-sm text-muted">Single-day non-working days for attendance and DTR.</p>
                </div>
                <span class="chip shrink-0">{{ $holidays->count() }} configured</span>
            </div>

            <div class="border-b border-line px-4 py-4 sm:px-5">
                <div class="alert-info text-xs sm:text-sm">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>
                        For multi-day events, meetings, and audience targeting, use
                        <a href="{{ route('admin.calendar.index') }}" class="font-semibold underline">Calendar &amp; Events</a>.
                        Holidays from both places are honoured by attendance and the DTR.
                    </span>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.settings.holidays.store') }}" class="border-b border-line form-card-body">
                @csrf
                <p class="text-sm font-semibold text-ink">Add holiday</p>
                <div class="form-grid-3 mt-3">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <label class="label" for="holiday-name">Name</label>
                        <input id="holiday-name" class="input" name="name" placeholder="e.g. National Heroes Day" required>
                    </div>
                    <div>
                        <label class="label" for="holiday-date">Date</label>
                        <input id="holiday-date" class="input" type="date" name="holiday_date" required>
                    </div>
                    <div>
                        <label class="label" for="holiday-type">Type</label>
                        <select id="holiday-type" class="select" name="type">
                            <option value="regular">Regular holiday</option>
                            <option value="special">Special non-working</option>
                        </select>
                    </div>
                </div>
                <button type="submit" class="btn-gold mt-4 w-full sm:w-auto">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add holiday
                </button>
            </form>

            <div class="form-card-body">
                @if ($holidays->isNotEmpty())
                    <div class="relative mb-4">
                        <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="search" class="input pl-9" placeholder="Filter holidays…" x-model="filter" aria-label="Filter holidays">
                    </div>
                @endif

                <div class="max-h-[min(24rem,55vh)] space-y-2 overflow-y-auto overscroll-contain pr-0.5">
                    @forelse ($holidays as $holiday)
                        @php
                            $isPast = $holiday->holiday_date->isPast() && ! $holiday->holiday_date->isToday();
                        @endphp
                        <div class="compact-list-item !flex-row items-center rounded-xl border border-gold-200 bg-gold-50/60 px-4 py-3 {{ $isPast ? 'opacity-75' : '' }}"
                             x-show="!filter || @js(strtolower($holiday->name)).includes(filter.toLowerCase())">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-bold text-ink">{{ $holiday->name }}</span>
                                    <span class="{{ $holiday->type === 'special' ? 'badge-gold' : 'badge-brand' }}">{{ ucfirst($holiday->type) }}</span>
                                </div>
                                <div class="mt-0.5 text-xs text-muted">{{ $holiday->holiday_date->format('D, M j, Y') }}</div>
                            </div>
                            <form method="POST" action="{{ route('admin.settings.holidays.destroy', $holiday) }}" class="shrink-0" onsubmit="return confirm('Remove this holiday?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-outline-danger btn-sm w-full sm:w-auto">Remove</button>
                            </form>
                        </div>
                    @empty
                        <x-empty-state title="No holidays configured" message="Add regular and special non-working days so attendance is computed correctly." icon="calendar" />
                    @endforelse
                </div>
            </div>
        </section>
    </div>

    @include('admin.settings.partials.smtp')

    @include('admin.settings.partials.mapbox')

    <section id="settings-admins" class="card scroll-mt-28 overflow-hidden">
        <div class="card-header">
            <div class="min-w-0">
                <h2 class="card-title text-base">Super admin accounts</h2>
                <p class="mt-0.5 text-sm text-muted">Full system access. Supervisor logins are managed under Employees.</p>
            </div>
            <span class="chip shrink-0">{{ $adminAccounts->count() }} account{{ $adminAccounts->count() === 1 ? '' : 's' }}</span>
        </div>

        <details class="border-b border-line group"
                 @if ($adminFormOpen) open @endif
                 x-data
                 x-init="if (window.matchMedia('(min-width: 1024px)').matches) $el.open = true">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-4 marker:content-none sm:px-5 lg:hidden [&::-webkit-details-marker]:hidden">
                <span class="text-sm font-semibold text-ink">Add super admin account</span>
                <svg class="h-5 w-5 shrink-0 text-muted transition group-open:rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
            </summary>
            <form method="POST" action="{{ route('admin.settings.admins.store') }}" class="form-card-body !pt-0 lg:!pt-5">
                @csrf
                <p class="mb-4 hidden text-sm font-semibold text-ink lg:block">Add super admin account</p>
                <div class="form-grid lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <label class="label" for="new-admin-name">Display name</label>
                        <input id="new-admin-name" class="input @error('name') input-error @enderror" name="name" value="{{ old('name') }}" placeholder="Jane Admin" required>
                        @error('name')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="new-admin-status">Status</label>
                        <select id="new-admin-status" class="select @error('status') input-error @enderror" name="status">
                            @foreach (\App\Enums\AccountStatus::cases() as $status)
                                <option value="{{ $status->value }}" @selected(old('status', 'active') === $status->value)>{{ $status->label() }}</option>
                            @endforeach
                        </select>
                        @error('status')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="label" for="new-admin-email">Email</label>
                        <input id="new-admin-email" class="input @error('email') input-error @enderror" type="email" name="email" value="{{ old('email') }}" inputmode="email" autocomplete="off" required>
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
                        <label class="label" for="new-admin-password-confirm">Confirm password</label>
                        <input id="new-admin-password-confirm" class="input" type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn-primary mt-4 w-full sm:w-auto">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add super admin
                </button>
            </form>
        </details>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Username</th>
                        <th class="hidden md:table-cell">Email</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adminAccounts as $account)
                        <tr>
                            <td>
                                <div class="font-semibold text-ink">{{ $account->name }}</div>
                                <div class="text-xs text-muted md:hidden">{{ $account->email }}</div>
                                @if ($account->id === auth()->id())
                                    <div class="text-xs font-medium text-brand-700">Signed in as you</div>
                                @endif
                            </td>
                            <td class="font-mono text-sm text-ink">{{ $account->username }}</td>
                            <td class="hidden text-sm text-muted md:table-cell">{{ $account->email }}</td>
                            <td>
                                <span class="{{ $account->status?->value === 'active' ? 'badge-brand' : 'badge-neutral' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $account->status?->label() }}
                                </span>
                            </td>
                            <td class="text-right">
                                <div class="action-group-stacked justify-end !flex-col sm:!flex-row">
                                    <a class="btn-outline-info btn-sm" href="{{ route('admin.settings.admins.edit', $account) }}">Edit</a>
                                    @if ($account->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.settings.admins.destroy', $account) }}" class="w-full sm:w-auto" onsubmit="return confirm('Delete super admin account «{{ $account->username }}»? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn-outline-danger btn-sm w-full sm:w-auto">Delete</button>
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
    </section>

    @include('admin.settings.partials.approval-workflow')
</div>
@endsection
