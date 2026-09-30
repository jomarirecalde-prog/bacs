@extends('layouts.app')
@section('title', $order->travel_order_number)
@section('page-title', 'Review Travel Order')

@section('content')
@include('travel-order.partials.details', ['order' => $order])
@if ($canEndorse)
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Endorsement action</h2></div>
        <form method="POST" action="{{ route('travel-order.approvals.decide', $order) }}" class="card-body space-y-3">@csrf
            <div class="flex gap-4">
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" required> Endorse</label>
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied"> Reject</label>
            </div>
            <textarea name="reason" class="textarea" rows="2" placeholder="Remarks (required if rejecting)"></textarea>
            <button class="btn btn-primary">Submit</button>
        </form>
    </div>
@endif
@endsection
