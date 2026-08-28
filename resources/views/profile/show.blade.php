@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('page-subtitle', 'Manage your personal information, photo, and account security')

@section('content')
@php
    $employee = $user->employee;
    $profileJson = $profile;
@endphp

<div class="mx-auto max-w-5xl space-y-6"
     x-data="profilePage(@js([
         'updateUrl' => route('profile.update'),
         'photoUploadUrl' => route('profile.photo.update'),
         'photoRemoveUrl' => route('profile.photo.remove'),
         'passwordUrl' => route('profile.password.update'),
         'profile' => $profileJson,
         'mustChangePassword' => (bool) $user->must_change_password,
     ]))"
     @profile-updated.window="applyProfile($event.detail)">

    {{-- Profile Overview --}}
    <div class="card card-accent-brand overflow-hidden">
        <div class="bg-gradient-to-br from-brand-700 via-brand-600 to-brand-800 px-6 py-8 text-white">
            <div class="flex flex-col items-center gap-5 sm:flex-row sm:items-start">
                <div class="relative shrink-0">
                    <img :src="avatarUrl" alt="" id="profile-avatar"
                         class="h-28 w-28 rounded-3xl object-cover ring-4 ring-white/30 shadow-float">
                    <span x-show="uploadingPhoto" x-cloak
                          class="absolute inset-0 flex items-center justify-center rounded-3xl bg-shell-950/45">
                        <svg class="h-8 w-8 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </span>
                </div>
                <div class="min-w-0 flex-1 text-center sm:text-left">
                    <h2 class="text-2xl font-extrabold tracking-tight" x-text="displayName">{{ $employee?->fullName() ?? $user->name }}</h2>
                    @if ($employee)
                        <p class="mt-1 text-brand-100" x-text="subtitle">{{ $employee->position }} · {{ $employee->department?->name }}</p>
                        <p class="mt-0.5 text-sm text-brand-200/80">Employee ID: <span class="font-semibold text-white" x-text="employeeNumber">{{ $employee->employee_number }}</span></p>
                    @else
                        <p class="mt-1 text-brand-100">{{ $user->email }}</p>
                    @endif
                    <div class="mt-3 flex flex-wrap items-center justify-center gap-2 sm:justify-start">
                        @if ($user->role)
                            <span class="badge-gold">{{ $user->role->label() }}</span>
                        @endif
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-bold {{ $user->isActive() ? 'bg-brand-500/30 text-white' : 'bg-critical-500/30 text-white' }}">
                            {{ $user->status?->label() ?? 'Unknown' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        @if ($employee)
            <div class="flex flex-wrap gap-2 border-t border-line bg-surface-50 px-5 py-4">
                <label class="btn btn-primary btn-sm cursor-pointer">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    Upload New Photo
                    <input type="file" class="hidden" accept="image/jpeg,image/png,image/webp" @change="uploadPhoto($event)">
                </label>
                <button type="button" class="btn btn-outline-info btn-sm" @click="removePhoto()" x-show="hasPhoto" x-cloak>
                    Remove Photo
                </button>
            </div>
        @endif
    </div>

    @if ($employee)
        {{-- Personal Information --}}
        <div class="card overflow-hidden">
            <div class="card-header flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="card-title">Personal Information</h2>
                    <p class="mt-0.5 text-sm text-muted">Update your contact details and personal data.</p>
                </div>
                <button type="button" class="btn btn-primary btn-sm shrink-0" x-show="!editing" @click="startEdit()">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Profile
                </button>
            </div>

            <div class="p-5" x-show="!editing">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <template x-for="row in readOnlyPersonal" :key="row.label">
                        <div>
                            <dt class="text-xs font-bold uppercase tracking-wide text-muted" x-text="row.label"></dt>
                            <dd class="mt-1 font-semibold text-ink" x-text="row.value || '—'"></dd>
                        </div>
                    </template>
                </dl>
            </div>

            <form x-show="editing" x-cloak @submit.prevent="saveProfile()" class="space-y-4 p-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label" for="first_name">First name <span class="text-critical-600">*</span></label>
                        <input id="first_name" class="input" x-model="form.first_name" required>
                        <p class="error-text" x-show="errors.first_name" x-text="errors.first_name"></p>
                    </div>
                    <div>
                        <label class="label" for="middle_name">Middle name</label>
                        <input id="middle_name" class="input" x-model="form.middle_name">
                    </div>
                    <div>
                        <label class="label" for="last_name">Last name <span class="text-critical-600">*</span></label>
                        <input id="last_name" class="input" x-model="form.last_name" required>
                        <p class="error-text" x-show="errors.last_name" x-text="errors.last_name"></p>
                    </div>
                    <div>
                        <label class="label" for="suffix">Suffix</label>
                        <input id="suffix" class="input" x-model="form.suffix" placeholder="Jr., Sr., III">
                    </div>
                    <div>
                        <label class="label" for="email">Personal email <span class="text-critical-600">*</span></label>
                        <input id="email" type="email" class="input" x-model="form.email" required>
                        <p class="error-text" x-show="errors.email" x-text="errors.email"></p>
                    </div>
                    <div>
                        <label class="label" for="contact_number">Contact number</label>
                        <input id="contact_number" class="input" x-model="form.contact_number" placeholder="09XX XXX XXXX">
                        <p class="error-text" x-show="errors.contact_number" x-text="errors.contact_number"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label" for="address">Address</label>
                        <textarea id="address" class="input min-h-[88px]" x-model="form.address" rows="2"></textarea>
                    </div>
                    <div>
                        <label class="label" for="birth_date">Birth date</label>
                        <input id="birth_date" type="date" class="input" x-model="form.birth_date">
                        <p class="error-text" x-show="errors.birth_date" x-text="errors.birth_date"></p>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="btn btn-primary" :disabled="saving">
                        <span x-show="!saving">Save Changes</span>
                        <span x-show="saving" x-cloak>Saving…</span>
                    </button>
                    <button type="button" class="btn btn-outline btn-sm" @click="cancelEdit()">Cancel</button>
                </div>
            </form>
        </div>

        {{-- Employment Information (read-only) --}}
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Employment Information</h2>
                <p class="mt-0.5 text-sm text-muted">Managed by Admin/HR. Contact your administrator to request changes.</p>
            </div>
            <dl class="grid gap-4 p-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                    ['Employee ID', $employee->employee_number],
                    ['Department', $employee->department?->name],
                    ['Position', $employee->position],
                    ['Employment Status', $employee->employment_status?->label()],
                    ['Date Hired', $employee->date_hired?->toFormattedDateString()],
                    ['Username', $user->username],
                ] as [$label, $value])
                    <div>
                        <dt class="text-xs font-bold uppercase tracking-wide text-muted">{{ $label }}</dt>
                        <dd class="mt-1 font-semibold text-ink">{{ $value ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @else
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Account Information</h2>
            </div>
            <dl class="grid gap-4 p-5 sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-muted">Name</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $user->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-muted">Email</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $user->email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-muted">Username</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $user->username }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-bold uppercase tracking-wide text-muted">Role</dt>
                    <dd class="mt-1 font-semibold text-ink">{{ $user->role?->label() }}</dd>
                </div>
            </dl>
        </div>
    @endif

    {{-- Change Password --}}
    <div id="password" class="card card-accent-brand overflow-hidden scroll-mt-24">
        <div class="card-header">
            <h2 class="card-title">Change Password</h2>
            <p class="mt-0.5 text-sm text-muted">Use a strong password with at least 8 characters.</p>
        </div>

        @if ($user->must_change_password)
            <div class="mx-5 mt-5 alert-warning">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                <span>You are using a temporary password. Please set a new password to continue.</span>
            </div>
        @endif

        <form @submit.prevent="changePassword()" class="space-y-4 p-5">
            <div>
                <label class="label" for="current_password">Current password</label>
                <input id="current_password" type="password" class="input" x-model="passwordForm.current_password" required autocomplete="current-password">
                <p class="error-text" x-show="passwordErrors.current_password" x-text="passwordErrors.current_password"></p>
            </div>
            <div>
                <label class="label" for="password">New password</label>
                <input id="password" type="password" class="input" x-model="passwordForm.password" required autocomplete="new-password" @input="updateStrength()">
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-surface-100">
                    <div class="h-full rounded-full transition-all duration-300"
                         :class="strengthClass"
                         :style="`width: ${strengthPercent}%`"></div>
                </div>
                <p class="mt-1 text-xs text-muted" x-text="strengthLabel"></p>
                <p class="error-text" x-show="passwordErrors.password" x-text="passwordErrors.password"></p>
            </div>
            <div>
                <label class="label" for="password_confirmation">Confirm new password</label>
                <input id="password_confirmation" type="password" class="input" x-model="passwordForm.password_confirmation" required autocomplete="new-password">
            </div>
            <button type="submit" class="btn btn-primary" :disabled="changingPassword">
                <span x-show="!changingPassword">Update Password</span>
                <span x-show="changingPassword" x-cloak>Updating…</span>
            </button>
        </form>
    </div>

    {{-- Account Security --}}
    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Account Security</h2>
        </div>
        <dl class="divide-y divide-line p-5 text-sm">
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Account status</dt>
                <dd class="font-semibold text-ink">{{ $user->status?->label() ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Last login</dt>
                <dd class="font-semibold text-ink">{{ $user->last_login_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Last password change</dt>
                <dd class="font-semibold text-ink" x-text="passwordChangedLabel">{{ $user->password_changed_at?->format('M j, Y g:i A') ?? '—' }}</dd>
            </div>
        </dl>
    </div>
</div>
@endsection
