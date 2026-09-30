@extends('layouts.app')
@section('title', $order->travel_order_number)
@section('page-title', 'Travel Order Details')
@section('page-subtitle', $order->travel_order_number)

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    @if ($canEdit)<a href="{{ route('employee.travel-orders.edit', $order) }}" class="btn btn-secondary">Edit</a>@endif
    @if ($canDownload)
        <a href="{{ route('employee.travel-orders.pdf', $order) }}" class="btn btn-primary">Download PDF</a>
        <a href="{{ route('employee.travel-orders.print', $order) }}" class="btn btn-secondary" target="_blank">Print</a>
    @endif
    @if ($canCancel)
        <form method="POST" action="{{ route('employee.travel-orders.cancel', $order) }}" onsubmit="return confirm('Cancel this travel order?')">@csrf<button class="btn btn-critical">Cancel request</button></form>
    @endif
</div>

@include('travel-order.partials.details', ['order' => $order])

@if ($canEndorse)
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Your endorsement</h2></div>
        <form method="POST" action="{{ route('travel-order.approvals.decide', $order) }}" class="card-body space-y-3">@csrf
            <div class="flex gap-3">
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" required> Endorse</label>
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied"> Reject</label>
            </div>
            <textarea name="reason" class="textarea" rows="2" placeholder="Remarks (required if rejecting)"></textarea>
            <button class="btn btn-primary">Submit decision</button>
        </form>
    </div>
@endif
@endsection
