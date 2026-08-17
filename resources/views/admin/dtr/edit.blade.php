@extends('layouts.app')

@section('title', 'Correct DTR')
@section('page-title', 'Correct DTR')
@section('page-subtitle', $attendance->employee?->fullName().' · '.$attendance->attendance_date->toFormattedDateString())

@section('content')
<div class="grid gap-6 lg:grid-cols-2">
    <div class="card p-6">
        <h2 class="font-bold mb-4">Original values</h2>
        <dl class="space-y-2 text-sm">
            <div class="flex justify-between"><dt>Time In</dt><dd>{{ $attendance->time_in?->format('h:i A') ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt>Time Out</dt><dd>{{ $attendance->time_out?->format('h:i A') ?? '—' }}</dd></div>
            <div class="flex justify-between"><dt>Status</dt><dd>{{ $attendance->status?->label() }}</dd></div>
        </dl>
        <p class="mt-4 text-xs text-slate-500">Original values are never overwritten silently. Every correction is stored in the change history.</p>
    </div>
    <div class="card p-6">
        <form method="POST" action="{{ route('admin.dtr.update', $attendance) }}" class="space-y-4" onsubmit="return confirm('Save this DTR correction? The original values will be kept in the audit trail.')">
            @csrf @method('PUT')
            <div><label class="label">New Time In</label><input class="input" type="time" name="time_in" value="{{ old('time_in', $attendance->time_in?->format('H:i')) }}"></div>
            <div><label class="label">New Time Out</label><input class="input" type="time" name="time_out" value="{{ old('time_out', $attendance->time_out?->format('H:i')) }}"></div>
            <div>
                <label class="label">Force status (optional)</label>
                <select class="input" name="forced_status">
                    <option value="">Calculate automatically</option>
                    <option value="on_leave">On Leave</option>
                    <option value="rest_day">Rest Day</option>
                    <option value="holiday">Holiday</option>
                    <option value="absent">Absent</option>
                </select>
            </div>
            <div><label class="label">Remarks</label><input class="input" name="remarks" value="{{ old('remarks', $attendance->remarks) }}"></div>
            <div><label class="label">Reason for modification (required)</label><textarea class="input" name="reason" rows="3" required>{{ old('reason') }}</textarea></div>
            <button class="btn-primary">Save correction</button>
        </form>
    </div>
</div>
@endsection
