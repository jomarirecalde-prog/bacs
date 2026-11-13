@extends('layouts.app')
@section('title', 'Official Business — Admin')
@section('page-title', 'Official Business Management')
@section('page-subtitle', 'Organization-wide official business requests')

@section('content')
<div class="space-y-6">
    <form method="GET" class="filter-bar">
        <div class="sm:min-w-[14rem] flex-[2]">
            <label class="label" for="admin-ob-q">Search</label>
            <input id="admin-ob-q" type="search" name="q" class="input" placeholder="Reference, requester, destination…" value="{{ request('q') }}">
        </div>
        <div class="sm:min-w-[11rem]">
            <label class="label" for="admin-ob-dept">Department</label>
            <select id="admin-ob-dept" name="department_id" class="select">
                <option value="">All departments</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[10rem]">
            <label class="label" for="admin-ob-destination">Destination</label>
            <input id="admin-ob-destination" type="search" name="destination" class="input" placeholder="From / to" value="{{ request('destination') }}">
        </div>
        <div class="sm:min-w-[10rem]">
            <label class="label" for="admin-ob-covered-date">Covered date</label>
            <input id="admin-ob-covered-date" type="date" name="covered_date" class="input" value="{{ request('covered_date') }}">
        </div>
        @if ($statusTab !== 'all')
            <input type="hidden" name="status" value="{{ $statusTab }}">
        @endif
        <button type="submit" class="btn-secondary">Filter</button>
    </form>

    <nav class="pill-tabs w-full overflow-x-auto">
        @foreach (['all' => 'All', 'pending_endorsement' => 'Pending Endorsement', 'pending_approval' => 'Pending Final Approval', 'approved' => 'Approved', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('admin.official-business.index', array_merge(request()->except('page', 'status'), ['status' => $key])) }}"
               class="{{ $statusTab === $key ? 'pill-tab-active' : 'pill-tab' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Requester</th>
                        <th>Participants</th>
                        <th>Department</th>
                        <th>Destination</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $ob)
                        @php
                            $names = $ob->participants->pluck('participant_name');
                        @endphp
                        <tr>
                            <td class="font-semibold text-ink" data-label="Reference">{{ $ob->reference_number }}</td>
                            <td data-label="Requester">{{ $ob->employee?->fullName() ?? '—' }}</td>
                            <td class="max-w-[12rem]" data-label="Participants">
                                <span class="line-clamp-2" title="{{ $names->implode(', ') }}">
                                    {{ $names->take(3)->implode(', ') }}{{ $names->count() > 3 ? ' +' . ($names->count() - 3) : '' }}
                                </span>
                            </td>
                            <td data-label="Department">{{ $ob->employee?->department?->name ?? '—' }}</td>
                            <td class="max-w-[14rem] truncate" data-label="Destination" title="{{ $ob->destinationLabel() }}">{{ $ob->destinationLabel() }}</td>
                            <td class="whitespace-nowrap" data-label="Dates">{{ $ob->dateRangeLabel() }}</td>
                            <td data-label="Status"><span class="{{ $ob->status->badgeClass() }}">{{ $ob->status->label() }}</span></td>
                            <td class="text-right" data-label="Action">
                                <a class="btn-outline btn-sm" href="{{ route('admin.official-business.show', $ob) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-0">
                                <x-empty-state title="No official business requests" message="Requests matching your filters will appear here." icon="document" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection
