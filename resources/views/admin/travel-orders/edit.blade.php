@extends('layouts.app')
@section('title', 'Edit '.$order->travel_order_number)
@section('page-title', 'Edit Approved Travel Order')
@section('page-subtitle', 'Super Admin full-form edit')

@section('content')
<form method="POST" action="{{ route('admin.travel-orders.update-approved', $order) }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @method('PUT')
    <div class="card card-accent-critical">
        <div class="card-body">
            <label class="label" for="edit_reason">Reason for modification <span class="text-critical-600">*</span></label>
            <textarea id="edit_reason" name="edit_reason" class="textarea" rows="2" required>{{ old('edit_reason') }}</textarea>
            @error('edit_reason') <p class="error-text">{{ $message }}</p> @enderror
        </div>
    </div>
    @include('travel-order.partials.form-fields', compact('employee', 'transportOptions', 'searchUrl', 'order', 'selectedTravelers'))
    <button type="submit" class="btn btn-primary">Save changes</button>
</form>
@endsection
