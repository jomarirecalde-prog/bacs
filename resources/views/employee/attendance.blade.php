@extends('layouts.app')

@section('title', 'My Attendance')
@section('page-title', 'My Attendance History')
@section('page-subtitle', 'Filter your recorded Time In and Time Out')

@section('content')
<div class="space-y-6">
    <form class="filter-bar">
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="att-from">From</label>
            <input id="att-from" type="date" name="date_from" value="{{ request('date_from') }}" class="input">
        </div>
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="att-to">To</label>
            <input id="att-to" type="date" name="date_to" value="{{ request('date_to') }}" class="input">
        </div>
        <div class="min-w-[10rem] flex-1">
            <label class="label" for="att-status">Status</label>
            <select id="att-status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach (\App\Enums\AttendanceStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Late</th>
                        <th class="text-right">Undertime</th>
                        <th class="text-right">Overtime</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink">{{ $row->attendance_date->format('M d, Y') }}</td>
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
                            <td class="text-right tabular-nums {{ $row->overtime_minutes > 0 ? 'font-bold text-gold-700' : 'text-muted' }}">{{ $row->overtimeHoursLabel() }}</td>
                            <td><x-status-badge :status="$row->status" /></td>
                            <td>
                                {{ $row->remarks ?: '—' }}
                                @if ($row->is_edited)<div class="mt-1"><span class="badge-warn">Edited by administrator</span></div>@endif
                                @if ($row->is_manual)<div class="mt-1"><span class="badge-neutral">Manual entry</span></div>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-0"><x-empty-state title="No attendance history" message="No records match the selected date range or status." icon="clock" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $records->links() }}</div>
    </div>
</div>
@endsection
