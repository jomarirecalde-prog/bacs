@extends('layouts.app')

@section('title', 'Manual DTR')
@section('page-title', 'Add Manual Time Entry')
@section('page-subtitle', 'Manual entries are flagged and recorded in the audit trail')

@section('content')
<div class="max-w-2xl space-y-4">
    <div class="alert-gold">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-gold-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Use manual entries only when a station scan could not be recorded. Every entry is marked as manual and attributed to your account.</span>
    </div>

    <div class="card card-accent-gold overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Time Entry</h2>
        </div>
        <form method="POST" action="{{ route('admin.dtr.store') }}" class="space-y-4 p-5">
            @csrf
            <div>
                <label class="label" for="employee_id">Employee</label>
                <select id="employee_id" class="select @error('employee_id') input-error @enderror" name="employee_id" required>
                    <option value="">Select employee</option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>{{ $emp->fullName() }} ({{ $emp->employee_number }})</option>
                    @endforeach
                </select>
                @error('employee_id')<p class="error-text">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="label" for="attendance_date">Date</label>
                <input id="attendance_date" class="input" type="date" name="attendance_date" value="{{ old('attendance_date', now()->toDateString()) }}" required>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label" for="time_in">Time In</label>
                    <input id="time_in" class="input" type="time" name="time_in" value="{{ old('time_in') }}">
                </div>
                <div>
                    <label class="label" for="time_out">Time Out</label>
                    <input id="time_out" class="input" type="time" name="time_out" value="{{ old('time_out') }}">
                </div>
            </div>
            <div>
                <label class="label" for="status">Status override (leave / rest / holiday)</label>
                <select id="status" class="select" name="status">
                    <option value="">Auto calculate</option>
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                    @endforeach
                </select>
                <p class="hint">Leave on auto calculate unless the day must be forced to a specific status.</p>
            </div>
            <div>
                <label class="label" for="remarks">Remarks</label>
                <textarea id="remarks" class="textarea" name="remarks" rows="3">{{ old('remarks') }}</textarea>
            </div>
            <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" class="btn-primary">Save entry</button>
                <a href="{{ route('admin.dtr.index') }}" class="btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
