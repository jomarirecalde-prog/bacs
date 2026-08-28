@extends('layouts.app')

@section('title', 'Station Monitoring')
@section('page-title', 'Station Monitoring')
@section('page-subtitle', 'Live status of attendance stations')

@section('content')
@php
    /* Presence tone: emerald = healthy, yellow = needs attention, neutral = idle/off. */
    $presenceTones = [
        'Online' => 'badge-brand',
        'Offline' => 'badge-neutral',
        'Locked' => 'badge-warn',
        'Unbound' => 'badge-warn',
        'Inactive' => 'badge-neutral',
    ];
@endphp

<div class="space-y-6">
    <div class="flex justify-end">
        <a href="{{ route('admin.stations.index') }}" class="btn-outline-info">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            Manage Stations
        </a>
    </div>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Station Status</h2>
            <span class="chip">{{ $stations->count() }} station(s)</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Station</th>
                        <th>Location</th>
                        <th>Device</th>
                        <th>Status</th>
                        <th>Last Scan</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stations as $station)
                        @php $presence = $station->presenceLabel(); @endphp
                        <tr class="{{ in_array($presence, ['Locked', 'Unbound'], true) ? 'row-attention' : '' }}">
                            <td>
                                <div class="font-semibold text-ink">{{ $station->station_name }}</div>
                                <div class="text-xs text-muted tabular-nums">{{ $station->station_code }}</div>
                            </td>
                            <td>{{ $station->location }}</td>
                            <td>
                                <span class="{{ $station->isBound() ? 'badge-brand' : 'badge-neutral' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $station->device_status->label() }}
                                </span>
                            </td>
                            <td>
                                <span class="{{ $presenceTones[$presence] ?? 'badge-neutral' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current {{ $presence === 'Online' ? 'animate-pulse' : 'opacity-80' }}"></span>
                                    {{ $presence }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap tabular-nums">{{ $station->last_scan_at?->timezone('Asia/Manila')->format('g:i A') ?? '—' }}</td>
                            <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('admin.stations.show', $station) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-0"><x-empty-state title="No stations" message="Create an attendance station first." icon="device" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Recent Station Scans</h2>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Time</th><th>Station</th><th>Employee</th><th>Action</th><th>Result</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-muted">{{ $log->scanned_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                            <td class="font-medium tabular-nums">{{ $log->station?->station_code ?? '—' }}</td>
                            <td class="font-medium text-ink">{{ $log->employee?->fullName() ?? '—' }}</td>
                            <td><span class="chip">{{ strtoupper($log->action) }}</span></td>
                            <td>
                                <span class="{{ $log->result?->value === 'success' ? 'badge-brand' : 'badge-warn' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $log->result?->label() }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="p-0"><x-empty-state title="No scans" message="No station activity yet." icon="device" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
