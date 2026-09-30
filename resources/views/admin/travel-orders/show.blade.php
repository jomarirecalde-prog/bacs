@extends('layouts.app')
@section('title', $order->travel_order_number)
@section('page-title', 'Travel Order — Admin')
@section('page-subtitle', $order->travel_order_number)

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    @if ($canAdminEdit)<a href="{{ route('admin.travel-orders.edit', $order) }}" class="btn btn-primary">Edit Travel Order</a>@endif
    @if ($canDownload)
        <a href="{{ route('admin.travel-orders.pdf', $order) }}" class="btn btn-secondary">Download PDF</a>
    @endif
    @if ($canAdminCancel)
        <button type="button" class="btn btn-critical" x-data x-on:click="$refs.cancelModal.showModal()">Cancel Travel Order</button>
    @endif
</div>

@include('travel-order.partials.details', ['order' => $order])

@if ($order->modificationLogs->isNotEmpty())
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Modification history</h2></div>
        <div class="card-body space-y-4">
            @foreach ($order->modificationLogs as $log)
                <div class="rounded-xl border border-line p-4 text-sm">
                    <div class="font-semibold">{{ $log->modifier?->name }} · {{ $log->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</div>
                    <div class="text-muted">Reason: {{ $log->reason }}</div>
                    <ul class="mt-2 list-disc pl-5">
                        @foreach ($log->changes as $change)
                            <li><strong>{{ $change['field'] }}</strong>: {{ is_array($change['previous'] ?? null) ? implode(', ', $change['previous']) : ($change['previous'] ?? '—') }} → {{ is_array($change['updated'] ?? null) ? implode(', ', $change['updated']) : ($change['updated'] ?? '—') }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
@endif

<dialog x-ref="cancelModal" class="rounded-2xl border border-line p-0 shadow-xl backdrop:bg-shell-950/50">
    <form method="POST" action="{{ route('admin.travel-orders.cancel', $order) }}" class="w-full max-w-lg p-6 space-y-4">@csrf
        <h3 class="text-lg font-bold">Cancel Travel Order</h3>
        <p class="text-sm text-muted">Travel Order Number: {{ $order->travel_order_number }}</p>
        <textarea name="reason" class="textarea" rows="3" required placeholder="Cancellation reason (required)"></textarea>
        <p class="text-sm">Are you sure you want to cancel this approved Travel Order? This action will mark the travel as cancelled and notify the concerned personnel.</p>
        <div class="flex justify-end gap-2">
            <button type="button" class="btn btn-secondary" onclick="this.closest('dialog').close()">Go back</button>
            <button class="btn btn-critical">Confirm cancellation</button>
        </div>
    </form>
</dialog>
@endsection
