@extends('layouts.app')

@section('title', 'Correct DTR')
@section('page-title', 'Correct DTR')
@section('page-subtitle', $attendance->employee?->fullName().' · '.$attendance->attendance_date->toFormattedDateString())

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card h-fit overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Original values</h2>
            <span class="badge-neutral">Read-only</span>
        </div>
        <dl class="divide-y divide-line px-5 text-sm">
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Time In</dt>
                <dd class="font-semibold text-ink tabular-nums">{{ $attendance->time_in?->format('h:i A') ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Time Out</dt>
                <dd class="font-semibold text-ink tabular-nums">{{ $attendance->time_out?->format('h:i A') ?? '—' }}</dd>
            </div>
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-muted">Status</dt>
                <dd><x-status-badge :status="$attendance->status" /></dd>
            </div>
        </dl>
        <div class="card-footer">
            <div class="alert-info">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs">Original values are never overwritten silently. Every correction is stored in the change history.</span>
            </div>
        </div>
    </div>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Apply correction</h2>
        </div>
        <form method="POST" action="{{ route('admin.dtr.update', $attendance) }}" class="space-y-4 p-5" onsubmit="return confirm('Save this DTR correction? The original values will be kept in the audit trail.')">
            @csrf @method('PUT')
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label" for="time_in">New Time In</label>
                    <input id="time_in" class="input" type="time" name="time_in" value="{{ old('time_in', $attendance->time_in?->format('H:i')) }}">
                </div>
                <div>
                    <label class="label" for="time_out">New Time Out</label>
                    <input id="time_out" class="input" type="time" name="time_out" value="{{ old('time_out', $attendance->time_out?->format('H:i')) }}">
                </div>
            </div>
            <div>
                <label class="label" for="forced_status">Force status (optional)</label>
                <select id="forced_status" class="select" name="forced_status">
                    <option value="">Calculate automatically</option>
                    <option value="on_leave">On Leave</option>
                    <option value="rest_day">Rest Day</option>
                    <option value="holiday">Holiday</option>
                    <option value="absent">Absent</option>
                </select>
            </div>
            <div>
                <label class="label" for="remarks">Remarks</label>
                <input id="remarks" class="input" name="remarks" value="{{ old('remarks', $attendance->remarks) }}">
            </div>
            <div>
                <label class="label" for="reason">Reason for modification (required)</label>
                <textarea id="reason" class="textarea @error('reason') input-error @enderror" name="reason" rows="3" required>{{ old('reason') }}</textarea>
                @error('reason')<p class="error-text">{{ $message }}</p>@enderror
                <p class="hint">This reason appears in the audit trail and DTR change history.</p>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" class="btn-primary">Save correction</button>
                <a href="{{ route('admin.dtr.show', $attendance) }}" class="btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
