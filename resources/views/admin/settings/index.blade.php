@extends('layouts.app')

@section('title', 'Settings')
@section('page-title', 'Settings')

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-6">
        <h2 class="font-bold mb-4">Organization</h2>
        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4">
            @csrf @method('PUT')
            <div><label class="label">Company name</label><input class="input" name="company_name" value="{{ $company }}" required></div>
            <div><label class="label">Company address</label><input class="input" name="company_address" value="{{ $address }}"></div>
            <p class="text-xs text-slate-500">Timezone is locked to Asia/Manila (Philippine Standard Time).</p>
            <button class="btn-primary">Save settings</button>
        </form>
    </div>
    <div class="card p-6">
        <h2 class="font-bold mb-4">Holidays</h2>
        <form method="POST" action="{{ route('admin.settings.holidays.store') }}" class="grid gap-2 md:grid-cols-3 mb-4">
            @csrf
            <input class="input" name="name" placeholder="Holiday name" required>
            <input class="input" type="date" name="holiday_date" required>
            <div class="flex gap-2">
                <select class="input" name="type">
                    <option value="regular">Regular</option>
                    <option value="special">Special</option>
                </select>
                <button class="btn-primary">Add</button>
            </div>
        </form>
        <div class="space-y-2">
            @forelse ($holidays as $holiday)
                <div class="flex items-center justify-between rounded-xl border border-slate-100 px-3 py-2">
                    <div>
                        <div class="text-sm font-semibold">{{ $holiday->name }}</div>
                        <div class="text-xs text-slate-500">{{ $holiday->holiday_date->toFormattedDateString() }} · {{ ucfirst($holiday->type) }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.settings.holidays.destroy', $holiday) }}">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600">Remove</button>
                    </form>
                </div>
            @empty
                <p class="text-sm text-slate-500">No holidays configured.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
