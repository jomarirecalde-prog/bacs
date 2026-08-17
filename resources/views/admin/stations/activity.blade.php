@extends('layouts.app')

@section('title', 'Station Activity')
@section('page-title', 'Station Activity Logs')
@section('page-subtitle', $station->station_code.' · '.$station->station_name)

@section('content')
<a href="{{ route('admin.stations.show', $station) }}" class="text-sm font-semibold text-brand-700">← Back to station</a>
<div class="mt-4 card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Employee</th>
                    <th>Action</th>
                    <th>Result</th>
                    <th>Reason</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->scanned_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                        <td>{{ $log->employee?->fullName() ?? '—' }}</td>
                        <td>{{ strtoupper($log->action) }}</td>
                        <td>{{ $log->result?->label() }}</td>
                        <td>{{ $log->failure_reason ?? '—' }}</td>
                        <td>{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state title="No logs" message="No station activity has been recorded." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $logs->links() }}</div>
</div>
@endsection
