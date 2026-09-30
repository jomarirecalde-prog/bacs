@extends('layouts.app')
@section('title', 'Travel Orders — Admin')
@section('page-title', 'Travel Order Management')
@section('page-subtitle', 'Organization-wide travel orders (Super Admin)')

@section('content')
<form method="GET" class="card mb-6">
    <div class="card-body grid gap-3 md:grid-cols-4">
        <input type="search" name="q" class="input" placeholder="Search TO #, requester, traveler…" value="{{ request('q') }}">
        <select name="department_id" class="select"><option value="">All departments</option>@foreach($departments as $dept)<option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>@endforeach</select>
        <input type="date" name="travel_date" class="input" value="{{ request('travel_date') }}">
        <button class="btn btn-primary">Filter</button>
    </div>
</form>

<div class="mb-4 flex flex-wrap gap-2">
    @foreach (['all'=>'All','pending_endorsement'=>'Pending Endorsement','pending_approval'=>'Pending Final Approval','approved'=>'Approved','cancelled'=>'Cancelled','rejected'=>'Rejected'] as $key=>$label)
        <a href="{{ route('admin.travel-orders.index', array_merge(request()->except('page'), ['status'=>$key])) }}" class="btn {{ $statusTab===$key?'btn-primary':'btn-secondary' }}">{{ $label }}</a>
    @endforeach
</div>

<div class="card overflow-x-auto">
    <table class="table text-sm">
        <thead><tr><th>TO #</th><th>Requester</th><th>Travelers</th><th>Destination</th><th>Dates</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($orders as $order)
            <tr>
                <td>{{ $order->travel_order_number }}</td>
                <td>{{ $order->requester?->fullName() }}</td>
                <td>{{ $order->personnel->map(fn($p)=>$p->employee?->fullName())->filter()->take(2)->implode(', ') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($order->destination,30) }}</td>
                <td>{{ $order->dateRangeLabel() }}</td>
                <td><span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span></td>
                <td><a class="btn btn-secondary btn-sm" href="{{ route('admin.travel-orders.show', $order) }}">Open</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="card-footer">{{ $orders->links() }}</div>
</div>
@endsection
