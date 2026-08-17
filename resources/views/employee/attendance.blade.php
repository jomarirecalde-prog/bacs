@extends('layouts.app')

@section('title', 'My Attendance')
@section('page-title', 'My Attendance History')

@section('content')
<form class="card p-4 grid gap-3 md:grid-cols-4 mb-6">
    <input type="date" name="date_from" value="{{ request('date_from') }}" class="input">
    <input type="date" name="date_to" value="{{ request('date_to') }}" class="input">
    <select name="status" class="input">
        <option value="">All statuses</option>
        @foreach (\App\Enums\AttendanceStatus::cases() as $status)
            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
        @endforeach
    </select>
    <button class="btn-primary">Filter</button>
</form>
<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Late</th><th>Undertime</th><th>Overtime</th><th>Status</th><th>Remarks</th></tr></thead>
            <tbody>
                @forelse ($records as $row)
                    <tr>
                        <td>{{ $row->attendance_date->format('M d, Y') }}</td>
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
                        <td>{{ $row->overtimeHoursLabel() }}</td>
                        <td><x-status-badge :status="$row->status" /></td>
                        <td>
                            {{ $row->remarks ?: '—' }}
                            @if ($row->is_edited)<div class="text-xs text-amber-700">Edited by administrator</div>@endif
                            @if ($row->is_manual)<div class="text-xs text-slate-500">Manual entry</div>@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9"><x-empty-state title="No attendance history" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $records->links() }}</div>
</div>
@endsection
