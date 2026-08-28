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
            @foreach ([
                'AM Time In' => $attendance->am_time_in,
                'AM Time Out' => $attendance->am_time_out,
                'PM Time In' => $attendance->pm_time_in,
                'PM Time Out' => $attendance->pm_time_out,
                'Overtime' => $attendance->overtime_in,
            ] as $label => $value)
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="font-semibold text-ink tabular-nums">{{ $value?->format('h:i A') ?? '—' }}</dd>
                </div>
            @endforeach
            <div class="flex items-center justify-between gap-4 py-3">
                <dt class="text-muted">Status</dt>
                <dd><x-status-badge :status="$attendance->status" /></dd>
            </div>
        </dl>
        <div class="card-footer">
            <div class="alert-info">
                <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span class="text-xs">Each field is corrected independently. Every change is stored in the audit trail with the attendance type affected.</span>
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
                    <label class="label" for="am_time_in">AM Time In</label>
                    <input id="am_time_in" class="input" type="time" name="am_time_in" value="{{ old('am_time_in', $attendance->am_time_in?->format('H:i')) }}">
                </div>
                <div>
                    <label class="label" for="am_time_out">AM Time Out</label>
                    <input id="am_time_out" class="input" type="time" name="am_time_out" value="{{ old('am_time_out', $attendance->am_time_out?->format('H:i')) }}">
                </div>
                <div>
                    <label class="label" for="pm_time_in">PM Time In</label>
                    <input id="pm_time_in" class="input" type="time" name="pm_time_in" value="{{ old('pm_time_in', $attendance->pm_time_in?->format('H:i')) }}">
                </div>
                <div>
                    <label class="label" for="pm_time_out">PM Time Out</label>
                    <input id="pm_time_out" class="input" type="time" name="pm_time_out" value="{{ old('pm_time_out', $attendance->pm_time_out?->format('H:i')) }}">
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <label class="label" for="overtime_in">Overtime</label>
                    <input id="overtime_in" class="input" type="time" name="overtime_in" value="{{ old('overtime_in', $attendance->overtime_in?->format('H:i')) }}">
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
