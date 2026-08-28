@extends('layouts.app')

@section('title', 'Station Activity')
@section('page-title', 'Station Activity Logs')
@section('page-subtitle', $station->station_code.' · '.$station->station_name)

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
                        <tr class="{{ $log->result?->value === 'failure' ? 'row-attention' : '' }}">
                            <td class="whitespace-nowrap text-muted">{{ $log->scanned_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td class="font-medium text-ink">{{ $log->employee?->fullName() ?? '—' }}</td>
                            <td><span class="chip">{{ strtoupper($log->action) }}</span></td>
                            <td>
                                <span class="{{ $log->result?->value === 'success' ? 'badge-brand' : 'badge-warn' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $log->result?->label() }}
                                </span>
                            </td>
                            <td>{{ $log->failure_reason ?? '—' }}</td>
                            <td class="text-muted tabular-nums">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-0"><x-empty-state title="No logs" message="No station activity has been recorded." icon="shield" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
