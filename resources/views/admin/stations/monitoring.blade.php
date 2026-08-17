@extends('layouts.app')

@section('title', 'Station Monitoring')
@section('page-title', 'Station Monitoring')
@section('page-subtitle', 'Live status of attendance stations')

@section('content')
<div class="flex justify-end mb-4">
    <a href="{{ route('admin.stations.index') }}" class="btn-secondary">Manage Stations</a>
</div>
<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Station</th>
                    <th>Location</th>
                    <th>Device</th>
                    <th>Status</th>
                    <th>Last Scan</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stations as $station)
                    <tr>
                        <td class="font-semibold">{{ $station->station_name }}<div class="text-xs text-slate-500">{{ $station->station_code }}</div></td>
                        <td>{{ $station->location }}</td>
                        <td>{{ $station->device_status->label() }}</td>
                        <td>
                            @php $presence = $station->presenceLabel(); @endphp
                            <span class="text-xs font-semibold uppercase
                                {{ $presence === 'Online' ? 'text-emerald-700' : '' }}
                                {{ $presence === 'Offline' ? 'text-slate-500' : '' }}
                                {{ $presence === 'Locked' ? 'text-red-700' : '' }}
                                {{ $presence === 'Unbound' ? 'text-amber-700' : '' }}
                                {{ $presence === 'Inactive' ? 'text-slate-400' : '' }}
                            ">{{ $presence }}</span>
                        </td>
                        <td>{{ $station->last_scan_at?->timezone('Asia/Manila')->format('g:i A') ?? '—' }}</td>
                        <td class="text-right"><a class="text-sm font-semibold text-brand-700" href="{{ route('admin.stations.show', $station) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty-state title="No stations" message="Create an attendance station first." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-6 card overflow-hidden">
    <div class="px-5 py-4 border-b font-bold">Recent Station Scans</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Time</th><th>Station</th><th>Employee</th><th>Action</th><th>Result</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td>{{ $log->scanned_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                        <td>{{ $log->station?->station_code ?? '—' }}</td>
                        <td>{{ $log->employee?->fullName() ?? '—' }}</td>
                        <td>{{ strtoupper($log->action) }}</td>
                        <td>{{ $log->result?->label() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5"><x-empty-state title="No scans" message="No station activity yet." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
