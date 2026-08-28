@extends('layouts.app')

@section('title', 'Station Attendance')
@section('page-title', 'Attendance from this Station')
@section('page-subtitle', $station->station_name)

@section('content')
<div class="space-y-4">
    <a href="{{ route('admin.stations.show', $station) }}" class="btn-outline btn-sm">
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Back to station
    </a>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Time In</th>
                        <th>In Station</th>
                        <th>Time Out</th>
                        <th>Out Station</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink">{{ $row->attendance_date?->format('M j, Y') }}</td>
                            <td class="font-semibold text-ink">{{ $row->employee?->fullName() }}</td>
                            <td class="font-medium text-brand-700 tabular-nums">{{ $row->time_in?->format('g:i A') ?? '—' }}</td>
                            <td class="text-muted">{{ $row->time_in_station_name ?? '—' }}</td>
                            <td class="font-medium text-info-700 tabular-nums">{{ $row->time_out?->format('g:i A') ?? '—' }}</td>
                            <td class="text-muted">{{ $row->time_out_station_name ?? '—' }}</td>
                            <td><x-status-badge :status="$row->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No attendance" message="This station has not recorded Time In or Time Out yet." icon="clock" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $records->links() }}</div>
    </div>
</div>
@endsection
