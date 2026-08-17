@extends('layouts.app')

@section('title', 'Station Attendance')
@section('page-title', 'Attendance from this Station')
@section('page-subtitle', $station->station_name)

@section('content')
<a href="{{ route('admin.stations.show', $station) }}" class="text-sm font-semibold text-brand-700">← Back to station</a>
<div class="mt-4 card overflow-hidden">
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
                        <td>{{ $row->attendance_date?->format('M j, Y') }}</td>
                        <td>{{ $row->employee?->fullName() }}</td>
                        <td>{{ $row->time_in?->format('g:i A') ?? '—' }}</td>
                        <td>{{ $row->time_in_station_name ?? '—' }}</td>
                        <td>{{ $row->time_out?->format('g:i A') ?? '—' }}</td>
                        <td>{{ $row->time_out_station_name ?? '—' }}</td>
                        <td><x-status-badge :status="$row->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state title="No attendance" message="This station has not recorded Time In or Time Out yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $records->links() }}</div>
</div>
@endsection
