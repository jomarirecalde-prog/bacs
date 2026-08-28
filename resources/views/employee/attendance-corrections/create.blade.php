@extends('layouts.app')

@section('title', 'Request DTR Correction')
@section('page-title', 'Request DTR Correction')
@section('page-subtitle', 'Correct a specific AM/PM or overtime entry')

@section('content')
<div class="max-w-2xl space-y-4">
    <div class="alert-info">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Select the exact attendance field to correct. After admin approval, only that field will be updated — other punches for the day remain unchanged.</span>
    </div>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Correction details</h2>
        </div>
        <form method="POST" action="{{ route('employee.attendance-corrections.store') }}" class="space-y-4 p-5">
            @csrf
            <div>
                <label class="label" for="attendance_date">Attendance date</label>
                <input id="attendance_date" class="input @error('attendance_date') input-error @enderror" type="date" name="attendance_date" value="{{ old('attendance_date', $date) }}" max="{{ now()->toDateString() }}" required>
                @error('attendance_date')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="punch_type">Attendance field to correct</label>
                <select id="punch_type" class="select @error('punch_type') input-error @enderror" name="punch_type" required>
                    <option value="">Select field</option>
                    @foreach ($punchTypes as $type)
                        @php($current = $originals[$type->value] ?? null)
                        <option value="{{ $type->value }}" @selected(old('punch_type') === $type->value)>
                            {{ $type->label() }} — current: {{ $current ? $current->format('h:i A') : 'Not recorded' }}
                        </option>
                    @endforeach
                </select>
                @error('punch_type')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="requested_time">Correct time</label>
                <input id="requested_time" class="input @error('requested_time') input-error @enderror" type="time" name="requested_time" value="{{ old('requested_time') }}" required>
                @error('requested_time')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="label" for="reason">Reason for correction</label>
                <textarea id="reason" class="textarea @error('reason') input-error @enderror" name="reason" rows="4" required placeholder="Explain why this entry needs to be corrected (e.g., forgot to scan, station error, meeting off-site).">{{ old('reason') }}</textarea>
                @error('reason')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" class="btn-primary">Submit request</button>
                <a href="{{ route('employee.attendance-corrections.index') }}" class="btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
