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
            <div class="card"><div class="card-body"><div class="text-xs font-bold uppercase text-muted">{{ $label }}</div><div class="text-2xl font-extrabold tabular-nums">{{ $value }}</div></div></div>
        @endforeach
    </div>

    <div class="flex flex-wrap gap-2">
        @foreach (['mine' => 'My Requests', 'endorsement' => 'For My Endorsement', 'approval' => 'For My Approval', 'participation' => 'Travel Participation'] as $key => $label)
            <a href="{{ route('employee.travel-orders.index', ['tab' => $key]) }}" class="btn {{ $tab === $key ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
        @endforeach
        <a href="{{ route('employee.travel-orders.create') }}" class="btn btn-primary ml-auto">New Travel Order</a>
    </div>

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>TO Number</th><th>Travelers</th><th>Destination</th><th>Dates</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($orders as $order)
                    <tr>
                        <td class="font-semibold">{{ $order->travel_order_number }}</td>
                        <td class="text-sm">{{ $order->personnel->map(fn($p) => $p->employee?->fullName())->filter()->take(2)->implode(', ') }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($order->destination, 40) }}</td>
                        <td>{{ $order->dateRangeLabel() }}</td>
                        <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                        <td><a class="btn btn-secondary btn-sm" href="{{ route('employee.travel-orders.show', $order) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-8">No travel orders in this view.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
