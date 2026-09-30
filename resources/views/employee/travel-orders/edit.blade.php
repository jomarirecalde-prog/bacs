@extends('layouts.app')
@section('title', 'Edit Travel Order')
@section('page-title', 'Edit Travel Order')
@section('page-subtitle', $order->travel_order_number)

@section('content')
<form method="POST" action="{{ route('employee.travel-orders.update', $order) }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @method('PUT')
    @include('travel-order.partials.form-fields', compact('employee', 'transportOptions', 'searchUrl', 'order', 'selectedTravelers'))
    <div class="flex flex-wrap gap-3">
        <button type="submit" name="action" value="draft" class="btn btn-secondary">Save draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-primary">Submit travel order</button>
    </div>
</form>
@endsection
