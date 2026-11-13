<div class="grid gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="card">
            <div class="card-header flex flex-wrap items-center justify-between gap-2">
                <h2 class="card-title">{{ $obRequest->reference_number }}</h2>
                <span class="{{ $obRequest->status->badgeClass() }}">{{ $obRequest->status->label() }}</span>
            </div>
            <div class="card-body space-y-4 text-sm">
                <div class="grid gap-3 sm:grid-cols-2">
                    <div><span class="text-muted">Requester</span><div class="font-semibold">{{ $obRequest->employee?->fullName() }}</div></div>
                    <div><span class="text-muted">Department</span><div>{{ $obRequest->employee?->department?->name ?? '—' }}</div></div>
                    <div><span class="text-muted">Date filed</span><div>{{ optional($obRequest->date_filed)->format('M j, Y') ?? '—' }}</div></div>
                    <div><span class="text-muted">Date covered</span><div>{{ $obRequest->dateRangeLabel() }}</div></div>
                    <div><span class="text-muted">Departure time</span><div>{{ filled($obRequest->departure_time) ? substr((string) $obRequest->departure_time, 0, 5) : '—' }}</div></div>
                    <div><span class="text-muted">Official station</span><div>{{ $obRequest->official_station ?: '—' }}</div></div>
                </div>

                <div>
                    <div class="label">Participants</div>
                    <ul class="mt-2 space-y-2">
                        @forelse ($obRequest->participants as $participant)
                            <li class="rounded-xl border border-line px-3 py-2">
                                <div class="font-semibold">{{ $participant->participant_name }}</div>
                                @if ($participant->employee)
                                    <div class="text-xs text-muted">{{ $participant->employee->position }} · {{ $participant->employee->department?->name }}</div>
                                @else
                                    <div class="text-xs text-muted">External participant</div>
                                @endif
                            </li>
                        @empty
                            <li class="text-muted">No participants listed.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2"><span class="text-muted">Destination</span><div>{{ $obRequest->destinationLabel() }}</div></div>
                    <div class="sm:col-span-2"><span class="text-muted">Purpose</span><div>{{ $obRequest->purpose }}</div></div>
                    @if ($obRequest->purpose_particular)
                        <div class="sm:col-span-2"><span class="text-muted">Particulars</span><div>{{ $obRequest->purpose_particular }}</div></div>
                    @endif
                    <div><span class="text-muted">Transportation</span><div>{{ $obRequest->transportationLabel() }}</div></div>
                    <div><span class="text-muted">Vehicle / plate</span><div>{{ trim(($obRequest->vehicle_name ?: '') . ' ' . ($obRequest->plate_number ?: '')) ?: '—' }}</div></div>
                    <div><span class="text-muted">Accommodation</span><div>{{ $obRequest->accommodation_type ?: '—' }}</div></div>
                    @if ($obRequest->accommodation_particular)
                        <div class="sm:col-span-2"><span class="text-muted">Accommodation particulars</span><div>{{ $obRequest->accommodation_particular }}</div></div>
                    @endif
                </div>

                @if ($obRequest->cancel_reason)
                    <div class="rounded-xl border border-critical-200 bg-critical-50 p-3 text-critical-800">
                        <span class="font-semibold">Cancellation reason:</span> {{ $obRequest->cancel_reason }}
                    </div>
                @endif
            </div>
        </div>
    </div>
    <div class="space-y-6">
        <div class="card card-accent-gold">
            <div class="card-header"><h2 class="card-title">Approval progress</h2></div>
            <div class="card-body">@include('official-business.partials.timeline', ['obRequest' => $obRequest])</div>
        </div>
    </div>
</div>
