@extends('layouts.app')
@section('title', 'Travel Order Endorsements')
@section('page-title', $mode === 'history' ? 'Travel Order Endorsement History' : 'Pending Travel Order Endorsements')

@section('content')
<div class="mb-4 flex gap-2">
    <a href="{{ route('travel-order.approvals.index') }}" class="btn {{ $mode === 'pending' ? 'btn-primary' : 'btn-secondary' }}">Pending</a>
    <a href="{{ route('travel-order.approvals.history') }}" class="btn {{ $mode === 'history' ? 'btn-primary' : 'btn-secondary' }}">History</a>
</div>
<div class="card overflow-hidden">
    <table class="table">
        <thead><tr><th>TO #</th><th>Requester</th><th>Destination</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($orders as $order)
            <tr>
                <td>{{ $order->travel_order_number }}</td>
                <td>{{ $order->requester?->fullName() }}</td>
                <td>{{ \Illuminate\Support\Str::limit($order->destination, 40) }}</td>
                <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                <td><a class="btn btn-secondary btn-sm" href="{{ route('travel-order.approvals.show', $order) }}">Review</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="py-8 text-center text-muted">No records.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
