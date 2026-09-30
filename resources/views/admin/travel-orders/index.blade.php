@extends('layouts.app')
@section('title', 'Travel Orders — Admin')
@section('page-title', 'Travel Order Management')
@section('page-subtitle', 'Organization-wide travel orders (Super Admin)')

@section('content')
<div class="space-y-6">
    <form method="GET" class="filter-bar">
        <div class="sm:min-w-[14rem] flex-[2]">
            <label class="label" for="admin-to-q">Search</label>
            <input id="admin-to-q" type="search" name="q" class="input" placeholder="TO number, requester, traveler…" value="{{ request('q') }}">
        </div>
        <div class="sm:min-w-[11rem]">
            <label class="label" for="admin-to-dept">Department</label>
            <select id="admin-to-dept" name="department_id" class="select">
                <option value="">All departments</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[10rem]">
            <label class="label" for="admin-to-travel-date">Travel date</label>
            <input id="admin-to-travel-date" type="date" name="travel_date" class="input" value="{{ request('travel_date') }}">
        </div>
        <div class="sm:min-w-[10rem]">
            <label class="label" for="admin-to-approved-from">Approved from</label>
            <input id="admin-to-approved-from" type="date" name="approved_from" class="input" value="{{ request('approved_from') }}">
        </div>
        <div class="sm:min-w-[10rem]">
            <label class="label" for="admin-to-approved-to">Approved to</label>
            <input id="admin-to-approved-to" type="date" name="approved_to" class="input" value="{{ request('approved_to') }}">
        </div>
        @if ($statusTab !== 'all')
            <input type="hidden" name="status" value="{{ $statusTab }}">
        @endif
        <button type="submit" class="btn-secondary">Filter</button>
    </form>

    <nav class="pill-tabs w-full overflow-x-auto">
        @foreach (['all' => 'All', 'pending_endorsement' => 'Pending Endorsement', 'pending_approval' => 'Pending Final Approval', 'approved' => 'Approved', 'cancelled' => 'Cancelled', 'rejected' => 'Rejected'] as $key => $label)
            <a href="{{ route('admin.travel-orders.index', array_merge(request()->except('page', 'status'), ['status' => $key])) }}"
               class="{{ $statusTab === $key ? 'pill-tab-active' : 'pill-tab' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>TO #</th>
                        <th>Requester</th>
                        <th>Travelers</th>
                        <th>Department</th>
                        <th>Destination</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $travelerNames = $order->personnel->map(fn ($p) => $p->employee?->fullName())->filter()->values();
                        @endphp
                        <tr>
                            <td class="font-semibold text-ink" data-label="TO #">{{ $order->travel_order_number }}</td>
                            <td data-label="Requester">{{ $order->requester?->fullName() ?? '—' }}</td>
                            <td class="max-w-[12rem]" data-label="Travelers">
                                <span class="line-clamp-2" title="{{ $travelerNames->implode(', ') }}">
                                    {{ $travelerNames->take(3)->implode(', ') }}{{ $travelerNames->count() > 3 ? ' +' . ($travelerNames->count() - 3) : '' }}
                                </span>
                            </td>
                            <td data-label="Department">{{ $order->requester?->department?->name ?? '—' }}</td>
                            <td class="max-w-[14rem] truncate" data-label="Destination" title="{{ $order->destination }}">{{ $order->destination ?: '—' }}</td>
                            <td class="whitespace-nowrap" data-label="Dates">{{ $order->dateRangeLabel() }}</td>
                            <td data-label="Status"><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td class="text-right" data-label="Action">
                                <a class="btn-outline btn-sm" href="{{ route('admin.travel-orders.show', $order) }}">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="p-0">
                                <x-empty-state title="No travel orders" message="Travel orders matching your filters will appear here." icon="document" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($orders->hasPages())
            <div class="card-footer">{{ $orders->links() }}</div>
        @endif
    </div>
</div>
@endsection
