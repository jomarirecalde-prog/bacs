@extends('layouts.app')
@section('title', 'Travel Order Endorsements')
@section('page-title', $mode === 'history' ? 'Travel Order Endorsement History' : 'Pending Travel Order Endorsements')
@section('page-subtitle', $mode === 'history' ? 'Travel orders you have already acted on' : 'Travel orders waiting for your endorsement')

@section('content')
<div class="space-y-6">
    <nav class="pill-tabs w-full sm:w-auto">
        <a href="{{ route('travel-order.approvals.index') }}" class="{{ $mode === 'pending' ? 'pill-tab-active' : 'pill-tab' }}">Pending</a>
        <a href="{{ route('travel-order.approvals.history') }}" class="{{ $mode === 'history' ? 'pill-tab-active' : 'pill-tab' }}">History</a>
    </nav>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>TO #</th>
                        <th>Requester</th>
                        <th>Department</th>
                        <th>Destination</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr class="{{ $order->status === \App\Enums\TravelOrderStatus::PendingSupervisor ? 'row-attention' : '' }}">
                            <td class="font-semibold text-ink" data-label="TO #">{{ $order->travel_order_number }}</td>
                            <td data-label="Requester">{{ $order->requester?->fullName() ?? '—' }}</td>
                            <td data-label="Department">{{ $order->requester?->department?->name ?? '—' }}</td>
                            <td class="max-w-[14rem] truncate" data-label="Destination" title="{{ $order->destination }}">{{ $order->destination ?: '—' }}</td>
                            <td class="whitespace-nowrap" data-label="Dates">{{ $order->dateRangeLabel() }}</td>
                            <td data-label="Status"><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                            <td class="text-right" data-label="Action">
                                <a class="btn-outline btn-sm" href="{{ route('travel-order.approvals.show', $order) }}">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-0">
                                <x-empty-state
                                    title="{{ $mode === 'history' ? 'No endorsement history' : 'No pending travel orders' }}"
                                    message="When a travel order is assigned to you for endorsement, it will appear here."
                                    icon="document"
                                />
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
