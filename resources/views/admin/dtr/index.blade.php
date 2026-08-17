@extends('layouts.app')

@section('title', 'DTR Management')
@section('page-title', 'Daily DTR')
@section('page-subtitle', 'Attendance for '.$date)

@section('content')
<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('admin.dtr.index') }}" class="btn-primary">Daily DTR</a>
    <a href="{{ route('admin.dtr.monthly') }}" class="btn-secondary">Monthly DTR</a>
    @if (auth()->user()->isAdmin())
        <a href="{{ route('admin.dtr.create') }}" class="btn-secondary">Manual entry</a>
    @endif
</div>
<form method="GET" class="card p-4 grid gap-3 md:grid-cols-5 mb-6">
    <input type="date" name="date" value="{{ $date }}" class="input">
    <select name="department_id" class="input">
        <option value="">All departments</option>
        @foreach ($departments as $dept)
            <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
        @endforeach
    </select>
    <select name="employee_id" class="input">
        <option value="">All employees</option>
        @foreach ($employees as $emp)
            <option value="{{ $emp->id }}" @selected(($filters['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
        @endforeach
    </select>
    <select name="status" class="input">
        <option value="">All statuses</option>
        @foreach ($statuses as $status)
            <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') == $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <div class="flex gap-2">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Search">
        <button class="btn-primary">Filter</button>
    </div>
</form>
<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th><th>Department</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Late</th><th>UT</th><th>OT</th><th>Status</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($records as $row)
                    <tr>
                        <td class="font-medium">{{ $row->employee?->fullName() }}</td>
                        <td>{{ $row->employee?->department?->name }}</td>
                        <td>
                            {{ $row->time_in?->format('h:i A') ?? '—' }}
                            @if ($row->time_in_station_name)
                                <div class="text-[11px] text-slate-500">{{ $row->time_in_station_name }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $row->time_out?->format('h:i A') ?? '—' }}
                            @if ($row->time_out_station_name)
                                <div class="text-[11px] text-slate-500">{{ $row->time_out_station_name }}</div>
                            @endif
                        </td>
                        <td>{{ $row->totalHoursLabel() }}</td>
                        <td>{{ $row->late_minutes }}</td>
                        <td>{{ $row->undertime_minutes }}</td>
                        <td>{{ $row->overtime_minutes }}</td>
                        <td><x-status-badge :status="$row->status" /></td>
                        <td class="text-right whitespace-nowrap">
                            <a class="text-sm font-semibold text-brand-700" href="{{ route('admin.dtr.show', $row) }}">View</a>
                            @if (auth()->user()->isAdmin())
                                <a class="text-sm font-semibold text-slate-600 ml-2" href="{{ route('admin.dtr.edit', $row) }}">Edit</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10"><x-empty-state title="No DTR records" message="No attendance found for this date and filter." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $records->links() }}</div>
</div>
@endsection
