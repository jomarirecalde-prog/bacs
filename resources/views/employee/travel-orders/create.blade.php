@extends('layouts.app')
@section('title', 'New Travel Order')
@section('page-title', 'Travel Order Request')
@section('page-subtitle', 'Official business travel')

@section('content')
<form method="POST" action="{{ route('employee.travel-orders.store') }}" enctype="multipart/form-data" class="space-y-6">
    @csrf
    @include('travel-order.partials.form-fields', ['employee' => $employee, 'transportOptions' => $transportOptions, 'searchUrl' => $searchUrl, 'selectedTravelers' => []])
    <div class="flex flex-wrap gap-3">
        <button type="submit" name="action" value="draft" class="btn btn-secondary">Save draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-primary">Submit travel order</button>
    </div>
</form>
@endsection
