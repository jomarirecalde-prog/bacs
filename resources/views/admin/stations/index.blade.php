@extends('layouts.app')

@section('title', 'Attendance Stations')
@section('page-title', 'Attendance Stations')
@section('page-subtitle', 'Create and manage device-bound QR attendance stations')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form class="filter-bar flex-1">
            <div class="sm:min-w-[14rem] flex-[2]">
                <label class="label" for="station-q">Search</label>
                <input id="station-q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Station ID, name, location">
            </div>
            <div class="sm:min-w-[9rem] flex-1">
                <label class="label" for="station-status">Status</label>
                <select id="station-status" name="status" class="select">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    <option value="locked" @selected(($filters['status'] ?? '') === 'locked')>Locked</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Search</button>
        </form>

        <div class="flex w-full flex-wrap gap-2 sm:w-auto sm:shrink-0">
            <a href="{{ route('admin.stations.monitoring') }}" class="btn-outline-info">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                Monitoring
            </a>
            <a href="{{ route('admin.stations.create') }}" class="btn-primary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create Station
            </a>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Station ID</th>
                        <th>Station Name</th>
                        <th>Location</th>
                        <th>Device</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($stations as $station)
                        <tr class="{{ $station->isLocked() ? 'row-attention' : '' }}">
                            <td class="font-bold text-ink tabular-nums">{{ $station->station_code }}</td>
                            <td class="font-medium">{{ $station->station_name }}</td>
                            <td>{{ $station->location }}</td>
                            <td>
                                <span class="{{ $station->isBound() ? 'badge-brand' : 'badge-neutral' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $station->device_status->label() }}
                                </span>
                            </td>
                            <td>
                                @php $stationStatus = $station->status->value; @endphp
                                <span class="{{ $stationStatus === 'active' ? 'badge-brand' : ($stationStatus === 'locked' ? 'badge-warn' : 'badge-neutral') }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $station->status->label() }}
                                </span>
                            </td>
                            <td class="whitespace-nowrap text-muted">{{ $station->last_seen_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <a class="btn-outline btn-sm" href="{{ route('admin.stations.show', $station) }}">Manage</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No stations" message="Create an attendance station to begin QR Time In / Time Out." icon="device" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $stations->links() }}</div>
    </div>
</div>
@endsection
