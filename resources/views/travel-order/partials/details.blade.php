<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card">
            <div class="card-header flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">{{ $order->travel_order_number }}</h2>
                <span class="{{ $order->status->badgeClass() }}">{{ $order->status->label() }}</span>
            </div>
            <div class="card-body space-y-4 text-sm">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><span class="text-muted">Requester</span><div class="font-semibold">{{ $order->requester?->fullName() }}</div></div>
                    <div><span class="text-muted">Department</span><div>{{ $order->requester?->department?->name ?? '—' }}</div></div>
                    <div><span class="text-muted">Date requested</span><div>{{ $order->requestedLabel() ?? '—' }}</div></div>
                    <div><span class="text-muted">Date covered</span><div>{{ $order->dateRangeLabel() }}</div></div>
                </div>
                <div>
                    <div class="label">Travel personnel (not including requester unless listed)</div>
                    <ul class="mt-2 space-y-2">
                        @forelse ($order->personnel as $person)
                            <li class="rounded-xl border border-line px-3 py-2">
                                <div class="font-semibold">{{ $person->employee?->fullName() }}</div>
                                <div class="text-xs text-muted">{{ $person->employee?->position }} · {{ $person->employee?->department?->name }}</div>
                            </li>
                        @empty
                            <li class="text-muted">No travelers selected.</li>
                        @endforelse
                    </ul>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><span class="text-muted">Official station</span><div>{{ $order->official_station ?: '—' }}</div></div>
                    <div><span class="text-muted">Number of BH</span><div>{{ $order->number_of_bh ?: '—' }}</div></div>
                    <div class="sm:col-span-2"><span class="text-muted">Destination/s</span><div>{{ $order->destination ?: '—' }}</div></div>
                    <div class="sm:col-span-2"><span class="text-muted">Purpose</span><div>{{ $order->purpose }}</div></div>
                    <div><span class="text-muted">Equipment</span><div>{{ $order->equipment ?: '—' }}</div></div>
                    <div><span class="text-muted">Project</span><div>{{ $order->project_name ?: '—' }}</div></div>
                    <div><span class="text-muted">Client / company</span><div>{{ $order->client_company ?: '—' }}</div></div>
                    <div><span class="text-muted">Transportation</span><div>{{ $order->transportationLabel() }}</div></div>
                    <div><span class="text-muted">Vehicle / plate</span><div>{{ trim(($order->vehicle_type ?: '') . ' ' . ($order->plate_number ?: '')) ?: '—' }}</div></div>
                    @if ($order->remarks)
                        <div class="sm:col-span-2"><span class="text-muted">Remarks</span><div>{{ $order->remarks }}</div></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div class="space-y-6">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Parallel endorsement</h2></div>
            <div class="card-body">@include('travel-order.partials.timeline', ['order' => $order])</div>
        </div>
    </div>
</div>
