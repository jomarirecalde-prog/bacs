@extends('layouts.app')
@section('title', 'Travel Orders')
@section('page-title', 'Travel Order Management')
@section('page-subtitle', 'Create and monitor official travel orders')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ([
            ['Total', $counts['total']],
            ['Pending Endorsement', $counts['pending_endorsement']],
            ['Pending Approval', $counts['pending_approval']],
            ['Approved', $counts['approved']],
            ['Rejected', $counts['rejected']],
        ] as [$label, $value])
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-bold uppercase tracking-wide text-muted">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-extrabold tabular-nums text-ink">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="pill-tabs w-full overflow-x-auto sm:w-auto">
            @foreach (['mine' => 'My Requests', 'endorsement' => 'For My Endorsement', 'approval' => 'For My Approval', 'participation' => 'Travel Participation'] as $key => $label)
                <a href="{{ route('employee.travel-orders.index', ['tab' => $key]) }}"
                   class="{{ $tab === $key ? 'pill-tab-active' : 'pill-tab' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <a href="{{ route('employee.travel-orders.create') }}" class="btn btn-primary shrink-0">New Travel Order</a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>TO Number</th>
                        <th>Travelers</th>
                        <th>Destination</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="font-semibold text-ink" data-label="TO Number">{{ $order->travel_order_number }}</td>
                            <td class="max-w-[14rem]" data-label="Travelers">
                                {{ $order->personnel->map(fn ($p) => $p->employee?->fullName())->filter()->take(2)->implode(', ') ?: '—' }}
                            </td>
                            <td class="max-w-[14rem] truncate" data-label="Destination" title="{{ $order->destination }}">{{ $order->destination ?: '—' }}</td>
                            <td class="whitespace-nowrap" data-label="Dates">{{ $order->dateRangeLabel() }}</td>
                            <td data-label="Status"><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td class="text-right" data-label="Action">
                                <a class="btn-outline btn-sm" href="{{ route('employee.travel-orders.show', $order) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state title="No travel orders" message="Create a new travel order or switch tabs to see other lists." icon="document" />
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
