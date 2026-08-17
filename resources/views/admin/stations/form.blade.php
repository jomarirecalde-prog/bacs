@extends('layouts.app')

@section('title', $station ? 'Edit Station' : 'Create Station')
@section('page-title', $station ? 'Edit Attendance Station' : 'Create Attendance Station')
@section('page-subtitle', 'Station passwords are hashed and never shown again')

@section('content')
<div class="max-w-2xl card p-6">
    <form method="POST" action="{{ $station ? route('admin.stations.update', $station) : route('admin.stations.store') }}" class="space-y-4">
        @csrf
        @if ($station) @method('PUT') @endif
        <div>
            <label class="label">Station Name</label>
            <input class="input" name="station_name" value="{{ old('station_name', $station?->station_name ?? '') }}" required placeholder="Main Office Attendance Station">
        </div>
        <div>
            <label class="label">Station ID</label>
            <input class="input" name="station_code" value="{{ old('station_code', $station?->station_code ?? $suggestedCode) }}" required placeholder="BACS-STATION-001">
            <p class="mt-1 text-xs text-slate-500">Must be unique. Letters, numbers, and hyphens only.</p>
        </div>
        <div>
            <label class="label">{{ $station ? 'New Station Password (optional)' : 'Station Password' }}</label>
            <input class="input" type="password" name="password" {{ $station ? '' : 'required' }} minlength="8">
        </div>
        <div>
            <label class="label">Confirm Password</label>
            <input class="input" type="password" name="password_confirmation" {{ $station ? '' : 'required' }} minlength="8">
        </div>
        <div>
            <label class="label">Location</label>
            <input class="input" name="location" value="{{ old('location', $station?->location ?? '') }}" required placeholder="Main Office Lobby">
        </div>
        <div>
            <label class="label">Description</label>
            <textarea class="input" name="description" rows="3">{{ old('description', $station?->description ?? '') }}</textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Status</label>
                <select class="input" name="status">
                    @foreach (\App\Enums\StationStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $station?->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Idle Timeout</label>
                <select class="input" name="idle_timeout_minutes">
                    @foreach (\App\Models\AttendanceStation::idleTimeoutOptions() as $minutes => $label)
                        <option value="{{ $minutes }}" @selected((int) old('idle_timeout_minutes', $station?->idle_timeout_minutes ?? 0) === $minutes)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="flex gap-2">
            <button class="btn-primary">{{ $station ? 'Save Station' : 'Create Station' }}</button>
            <a class="btn-secondary" href="{{ $station ? route('admin.stations.show', $station) : route('admin.stations.index') }}">Cancel</a>
        </div>
    </form>
</div>
@endsection
