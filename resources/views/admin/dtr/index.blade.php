@extends('layouts.app')

@section('title', 'DTR Management')
@section('page-title', 'Daily DTR')
@section('page-subtitle', 'Attendance for '.$date)

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="pill-tabs">
            <a href="{{ route('admin.dtr.index') }}" class="pill-tab-active">Daily DTR</a>
            <a href="{{ route('admin.dtr.monthly') }}" class="pill-tab">Monthly DTR</a>
        </nav>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.dtr.create') }}" class="btn-gold btn-sm">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Manual entry
            </a>
        @endif
    </div>

    <form method="GET" class="filter-bar">
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="dtr-date">Date</label>
            <input id="dtr-date" type="date" name="date" value="{{ $date }}" class="input">
        </div>
        <div class="min-w-[10rem] flex-1">
            <label class="label" for="dtr-dept">Department</label>
            <select id="dtr-dept" name="department_id" class="select">
                <option value="">All departments</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[10rem] flex-1">
            <label class="label" for="dtr-employee">Employee</label>
            <select id="dtr-employee" name="employee_id" class="select">
                <option value="">All employees</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected(($filters['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="dtr-status">Status</label>
            <select id="dtr-status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') == $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[10rem] flex-1">
            <label class="label" for="dtr-q">Search</label>
            <input id="dtr-q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Name or ID">
        </div>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Late</th>
                        <th class="text-right">UT</th>
                        <th class="text-right">OT</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr class="{{ $row->time_in && ! $row->time_out ? 'row-attention' : '' }}">
                            <td class="font-semibold text-ink">{{ $row->employee?->fullName() }}</td>
                            <td>{{ $row->employee?->department?->name ?? '—' }}</td>
                            <td>
                                <span class="font-medium text-brand-700 tabular-nums">{{ $row->time_in?->format('h:i A') ?? '—' }}</span>
                                @if ($row->time_in_station_name)
                                    <div class="text-[11px] text-muted">{{ $row->time_in_station_name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="font-medium text-info-700 tabular-nums">{{ $row->time_out?->format('h:i A') ?? '—' }}</span>
                                @if ($row->time_out_station_name)
                                    <div class="text-[11px] text-muted">{{ $row->time_out_station_name }}</div>
                                @endif
                            </td>
                            <td class="text-right font-semibold text-ink tabular-nums">{{ $row->totalHoursLabel() }}</td>
                            <td class="text-right tabular-nums {{ $row->late_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->late_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->undertime_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->undertime_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->overtime_minutes > 0 ? 'font-bold text-gold-700' : 'text-muted' }}">{{ $row->overtime_minutes }}</td>
                            <td><x-status-badge :status="$row->status" /></td>
                            <td class="whitespace-nowrap text-right">
                                <a class="btn-outline btn-sm" href="{{ route('admin.dtr.show', $row) }}">View</a>
                                @if (auth()->user()->isAdmin())
                                    <a class="btn-outline-info btn-sm ml-1" href="{{ route('admin.dtr.edit', $row) }}">Edit</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-0"><x-empty-state title="No DTR records" message="No attendance found for this date and filter." icon="clock" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $records->links() }}</div>
    </div>
</div>
@endsection
