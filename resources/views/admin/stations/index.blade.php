@extends('layouts.app')

@section('title', 'Attendance Stations')
@section('page-title', 'Attendance Stations')
@section('page-subtitle', 'Create and manage device-bound QR attendance stations')

@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <form class="flex flex-1 flex-wrap gap-2">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="input max-w-xs" placeholder="Search station ID, name, location">
        <select name="status" class="input max-w-[160px]">
            <option value="">All statuses</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            <option value="locked" @selected(($filters['status'] ?? '') === 'locked')>Locked</option>
        </select>
        <button class="btn-secondary">Search</button>
    </form>
    <div class="flex gap-2">
        <a href="{{ route('admin.stations.monitoring') }}" class="btn-secondary">Station Monitoring</a>
        <a href="{{ route('admin.stations.create') }}" class="btn-primary">Create Station</a>
    </div>
</div>

<div class="mt-6 card overflow-hidden">
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($stations as $station)
                    <tr>
                        <td class="font-semibold">{{ $station->station_code }}</td>
                        <td>{{ $station->station_name }}</td>
                        <td>{{ $station->location }}</td>
                        <td>
                            <span class="text-xs font-semibold uppercase {{ $station->isBound() ? 'text-emerald-700' : 'text-slate-500' }}">
                                {{ $station->device_status->label() }}
                            </span>
                        </td>
                        <td>{{ $station->status->label() }}</td>
                        <td>{{ $station->last_seen_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</td>
                        <td class="text-right whitespace-nowrap">
                            <a class="text-sm font-semibold text-brand-700" href="{{ route('admin.stations.show', $station) }}">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state title="No stations" message="Create an attendance station to begin QR Time In / Time Out." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $stations->links() }}</div>
</div>
@endsection
