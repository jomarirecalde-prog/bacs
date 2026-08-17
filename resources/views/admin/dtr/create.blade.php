@extends('layouts.app')

@section('title', 'Manual DTR')
@section('page-title', 'Add Manual Time Entry')

@section('content')
<div class="card p-6 max-w-2xl">
    <form method="POST" action="{{ route('admin.dtr.store') }}" class="space-y-4">
        @csrf
        <div>
            <label class="label">Employee</label>
            <select class="input" name="employee_id" required>
                <option value="">Select employee</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected(old('employee_id') == $emp->id)>{{ $emp->fullName() }} ({{ $emp->employee_number }})</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Date</label><input class="input" type="date" name="attendance_date" value="{{ old('attendance_date', now()->toDateString()) }}" required></div>
        <div class="grid grid-cols-2 gap-4">
            <div><label class="label">Time In</label><input class="input" type="time" name="time_in" value="{{ old('time_in') }}"></div>
            <div><label class="label">Time Out</label><input class="input" type="time" name="time_out" value="{{ old('time_out') }}"></div>
        </div>
        <div>
            <label class="label">Status override (leave / rest / holiday)</label>
            <select class="input" name="status">
                <option value="">Auto calculate</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}">{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div><label class="label">Remarks</label><textarea class="input" name="remarks" rows="3">{{ old('remarks') }}</textarea></div>
        <button class="btn-primary">Save entry</button>
    </form>
</div>
@endsection
