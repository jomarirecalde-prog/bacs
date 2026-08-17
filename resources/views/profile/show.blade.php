@extends('layouts.app')

@section('title', 'My Profile')
@section('page-title', 'My Profile')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-6">
        @if ($user->employee)
            <img src="{{ $user->employee->photoUrl() }}" alt="" class="h-20 w-20 rounded-2xl object-cover">
            <h2 class="mt-4 text-xl font-bold">{{ $user->employee->fullName() }}</h2>
            <p class="text-sm text-slate-500">{{ $user->employee->position }} · {{ $user->employee->department?->name }}</p>
            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Employee No.</dt><dd>{{ $user->employee->employee_number }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd>{{ $user->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Username</dt><dd>{{ $user->username }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Date hired</dt><dd>{{ $user->employee->date_hired?->toFormattedDateString() ?: '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Role</dt><dd>{{ $user->role?->label() }}</dd></div>
            </dl>
            <form method="POST" action="{{ route('profile.update') }}" class="mt-6 space-y-3">
                @csrf @method('PUT')
                <div>
                    <label class="label">Contact number</label>
                    <input class="input" name="contact_number" value="{{ old('contact_number', $user->employee->contact_number) }}">
                    @error('contact_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button class="btn-secondary">Update contact</button>
            </form>
        @else
            <h2 class="text-xl font-bold">{{ $user->name }}</h2>
            <p class="text-sm text-slate-500">{{ $user->email }} · {{ $user->role?->label() }}</p>
        @endif
        <a href="{{ route('profile.password') }}" class="btn-secondary mt-6 inline-flex">Change password</a>
    </div>
</div>
@endsection
