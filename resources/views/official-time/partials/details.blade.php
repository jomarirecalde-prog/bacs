<div class="card">
    <div class="card-header flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="card-title">{{ $ot->request_no }}</h2>
            <p class="text-sm text-muted">{{ $ot->officialTimeType?->name }} · {{ $ot->date?->format('M j, Y') }}</p>
        </div>
        <span class="{{ $ot->status->badgeClass() }}">{{ $ot->status->label() }}</span>
    </div>
    <div class="card-body grid gap-6 lg:grid-cols-2">
        <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-muted">Employee Information</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">Name</dt><dd class="font-semibold text-ink">{{ $ot->employee?->fullName() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Employee No.</dt><dd class="font-mono">{{ $ot->employee?->employee_number }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Department</dt><dd>{{ $ot->department?->name ?? $ot->employee?->department?->name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Designation</dt><dd>{{ $ot->designation?->name ?? $ot->employee?->designation?->name ?? '—' }}</dd></div>
            </dl>
        </div>
        <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-muted">Official Time Information</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">Date</dt><dd>{{ $ot->date?->format('M j, Y') }}</dd></div>
                <div class="grid grid-cols-2 gap-x-4 gap-y-2 sm:grid-cols-4">
                    <div><dt class="text-muted">AM Time In</dt><dd class="mt-1 font-medium tabular-nums">{{ $ot->am_time_in ? \App\Support\ManilaTime::parse('1970-01-01 '.$ot->am_time_in)->format('g:i A') : '—' }}</dd></div>
                    <div><dt class="text-muted">AM Time Out</dt><dd class="mt-1 font-medium tabular-nums">{{ $ot->am_time_out ? \App\Support\ManilaTime::parse('1970-01-01 '.$ot->am_time_out)->format('g:i A') : '—' }}</dd></div>
                    <div><dt class="text-muted">PM Time In</dt><dd class="mt-1 font-medium tabular-nums">{{ $ot->pm_time_in ? \App\Support\ManilaTime::parse('1970-01-01 '.$ot->pm_time_in)->format('g:i A') : '—' }}</dd></div>
                    <div><dt class="text-muted">PM Time Out</dt><dd class="mt-1 font-medium tabular-nums">{{ $ot->pm_time_out ? \App\Support\ManilaTime::parse('1970-01-01 '.$ot->pm_time_out)->format('g:i A') : '—' }}</dd></div>
                </div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Duration</dt><dd class="font-semibold">{{ $ot->durationLabel() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Type</dt><dd>{{ $ot->officialTimeType?->name }}</dd></div>
                <div><dt class="text-muted">Purpose</dt><dd class="mt-1">{{ $ot->purpose }}</dd></div>
                @if ($ot->activity)<div><dt class="text-muted">Activity</dt><dd class="mt-1">{{ $ot->activity }}</dd></div>@endif
                @if ($ot->location)<div><dt class="text-muted">Location</dt><dd class="mt-1">{{ $ot->location }}</dd></div>@endif
                @if ($ot->remarks)<div><dt class="text-muted">Remarks</dt><dd class="mt-1">{{ $ot->remarks }}</dd></div>@endif
                @if ($canDownload ?? false)
                    <div class="pt-2">
                        <a class="btn-outline btn-sm" href="{{ route('employee.official-time.attachment', $ot) }}">Download supporting document</a>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>

@if ($ot->actions->isNotEmpty())
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Approval History</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Status</th><th>Remarks</th></tr></thead>
                <tbody>
                    @foreach ($ot->actions as $action)
                        <tr>
                            <td>{{ \App\Support\ManilaTime::formatDateTime($action->acted_at, 'M j, Y g:i A') }}</td>
                            <td>{{ $action->user?->name }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $action->action)) }}</td>
                            <td>{{ $action->new_status?->label() ?? '—' }}</td>
                            <td>{{ $action->reason ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
