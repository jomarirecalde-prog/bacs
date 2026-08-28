@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('page-subtitle', 'Your account details and contact information')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card card-accent-brand overflow-hidden">
        @if ($user->employee)
            <div class="p-6">
                <div class="flex items-start gap-4">
                    <img src="{{ $user->employee->photoUrl() }}" alt="" class="h-20 w-20 shrink-0 rounded-2xl object-cover ring-2 ring-brand-100">
                    <div class="min-w-0 pt-1">
                        <h2 class="text-xl font-extrabold tracking-tight text-ink">{{ $user->employee->fullName() }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ $user->employee->position }} · {{ $user->employee->department?->name }}</p>
                        @if ($user->role)
                            <span class="badge-info mt-2">{{ $user->role->label() }}</span>
                        @endif
                    </div>
                </div>

                <dl class="mt-6 divide-y divide-line text-sm">
                    @foreach ([
                        ['Employee No.', $user->employee->employee_number],
                        ['Email', $user->email],
                        ['Username', $user->username],
                        ['Date hired', $user->employee->date_hired?->toFormattedDateString() ?: '—'],
                        ['Role', $user->role?->label()],
                    ] as [$term, $definition])
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="shrink-0 text-muted">{{ $term }}</dt>
                            <dd class="text-right font-semibold text-ink">{{ $definition ?: '—' }}</dd>
                        </div>
                    @endforeach
                </dl>

                <form method="POST" action="{{ route('profile.update') }}" class="mt-6">
                    @csrf @method('PUT')
                    <div>
                        <label class="label" for="contact_number">Contact number</label>
                        <input id="contact_number" class="input @error('contact_number') input-error @enderror" name="contact_number" value="{{ old('contact_number', $user->employee->contact_number) }}">
                        @error('contact_number')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="btn-primary mt-4">Update contact</button>
                </form>
            </div>
        @else
            <div class="p-6">
                <div class="flex items-center gap-4">
                    <div class="brand-mark h-16 w-16 rounded-2xl text-xl">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                    <div class="min-w-0">
                        <h2 class="text-xl font-extrabold tracking-tight text-ink">{{ $user->name }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ $user->email }}</p>
                        @if ($user->role)
                            <span class="badge-info mt-2">{{ $user->role->label() }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <div class="card-footer">
            <a href="{{ route('profile.password') }}" class="btn-outline-info btn-sm">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                Change password
            </a>
        </div>
    </div>
</div>
@endsection
