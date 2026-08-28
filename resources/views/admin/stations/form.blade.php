@extends('layouts.app')

@section('title', $station ? 'Edit Station' : 'Create Station')
@section('page-title', $station ? 'Edit Attendance Station' : 'Create Attendance Station')
@section('page-subtitle', 'Station passwords are hashed and never shown again')

@section('content')
<div class="max-w-2xl space-y-4">
    <div class="alert-warning">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
        <span>Store the station password securely before saving. It is hashed and cannot be retrieved later.</span>
    </div>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">{{ $station ? 'Station Settings' : 'New Station' }}</h2>
        </div>
        <form method="POST" action="{{ $station ? route('admin.stations.update', $station) : route('admin.stations.store') }}" class="space-y-4 p-5">
            @csrf
            @if ($station) @method('PUT') @endif

            <div>
                <label class="label" for="station_name">Station Name</label>
                <input id="station_name" class="input @error('station_name') input-error @enderror" name="station_name" value="{{ old('station_name', $station?->station_name ?? '') }}" required placeholder="Main Office Attendance Station">
                @error('station_name')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="station_code">Station ID</label>
                <input id="station_code" class="input @error('station_code') input-error @enderror" name="station_code" value="{{ old('station_code', $station?->station_code ?? $suggestedCode) }}" required placeholder="BACS-STATION-001">
                <p class="hint">Must be unique. Letters, numbers, and hyphens only.</p>
                @error('station_code')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="station-password">{{ $station ? 'New Station Password (optional)' : 'Station Password' }}</label>
                    <input id="station-password" class="input @error('password') input-error @enderror" type="password" name="password" {{ $station ? '' : 'required' }} minlength="8" autocomplete="new-password">
                    @error('password')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="station-password-confirm">Confirm Password</label>
                    <input id="station-password-confirm" class="input" type="password" name="password_confirmation" {{ $station ? '' : 'required' }} minlength="8" autocomplete="new-password">
                </div>
            </div>
            <div>
                <label class="label" for="location">Location</label>
                <input id="location" class="input @error('location') input-error @enderror" name="location" value="{{ old('location', $station?->location ?? '') }}" required placeholder="Main Office Lobby">
                @error('location')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="description">Description</label>
                <textarea id="description" class="textarea" name="description" rows="3">{{ old('description', $station?->description ?? '') }}</textarea>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label" for="status">Status</label>
                    <select id="status" class="select" name="status">
                        @foreach (\App\Enums\StationStatus::cases() as $status)
                            <option value="{{ $status->value }}" @selected(old('status', $station?->status?->value ?? 'active') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label" for="idle_timeout_minutes">Idle Timeout</label>
                    <select id="idle_timeout_minutes" class="select" name="idle_timeout_minutes">
                        @foreach (\App\Models\AttendanceStation::idleTimeoutOptions() as $minutes => $label)
                            <option value="{{ $minutes }}" @selected((int) old('idle_timeout_minutes', $station?->idle_timeout_minutes ?? 0) === $minutes)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" class="btn-primary">{{ $station ? 'Save Station' : 'Create Station' }}</button>
                <a class="btn-outline" href="{{ $station ? route('admin.stations.show', $station) : route('admin.stations.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
