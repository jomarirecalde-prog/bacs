@extends('layouts.app')
@section('title', 'OT Attachment Reports')
@section('page-title', 'OT Attachment Reports')
@section('page-subtitle', 'Document overtime activities for payroll OT rows from your DTR')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ([
            ['Total', $counts['total']],
            ['Pending', $counts['pending']],
            ['Approved', $counts['approved']],
            ['Rejected', $counts['rejected']],
            ['Returned', $counts['returned']],
            ['Cancelled', $counts['cancelled']],
        ] as [$label, $value])
            <div class="card"><div class="card-body">
                <div class="text-xs font-bold uppercase tracking-wide text-muted">{{ $label }}</div>
                <div class="mt-1 text-2xl font-extrabold tabular-nums">{{ $value }}</div>
            </div></div>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <p class="text-sm text-muted max-w-2xl">
            Step 1: <strong class="text-ink">Request Official Time</strong> (OT application).
            Step 2: after payroll/DTR shows an overtime row, <strong class="text-ink">create the attachment report</strong> for that date.
        </p>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="{{ route('employee.official-time.create') }}" class="btn btn-primary">Request Official Time (OT)</a>
            @if ($eligibleOvertime->isNotEmpty())
                <a href="#eligible-ot" class="btn-outline">Create attachment report</a>
            @endif
        </div>
    </div>

    @if ($eligibleOvertime->isNotEmpty())
        <div id="eligible-ot" class="card overflow-hidden scroll-mt-4">
            <div class="card-header">
                <h2 class="card-title">DTR overtime rows needing attachment</h2>
                <p class="text-xs text-muted">Pick a date to file your OT Attachment Report. Payroll reviews reports on the period overtime page.</p>
            </div>
            <div class="table-wrap">
                <table class="data-table text-sm">
                    <thead><tr><th>Date</th><th class="text-right">Recorded OT</th><th>Payroll OT status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($eligibleOvertime as $ot)
                            @php $fmt = sprintf('%d:%02d', intdiv($ot->recorded_minutes, 60), $ot->recorded_minutes % 60); @endphp
                            <tr>
                                <td class="font-medium">{{ $ot->attendance_date->format('M j, Y') }}</td>
                                <td class="text-right tabular-nums">{{ $fmt }}</td>
                                <td>{{ $ot->status?->label() }}</td>
                                <td class="text-right">
                                    <a class="btn btn-primary btn-sm" href="{{ route('employee.overtime-attachment.create', ['overtime_request_id' => $ot->id]) }}">Create report</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body text-sm text-muted">
                No payroll overtime row is waiting for an attachment right now.
                If you need OT for a new date, use <a class="font-semibold text-brand-700" href="{{ route('employee.official-time.create') }}">Request Official Time</a> first, then return here once that date appears under eligible rows (or clock OT on your DTR).
            </div>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Report No.</th>
                        <th>DTR OT date</th>
                        <th>Coverage</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $row)
                        <tr>
                            <td class="font-semibold">{{ $row->report_no }}</td>
                            <td>{{ $row->overtimeRequest?->attendance_date?->format('M j, Y') ?? '—' }}</td>
                            <td>{{ $row->coverageLabel() }}</td>
                            <td>{{ $row->durationLabel() }}</td>
                            <td><span class="{{ $row->status->badgeClass() }}">{{ $row->status->label() }}</span></td>
                            <td>{{ $row->submittedLabel() ?? '—' }}</td>
                            <td class="text-right space-x-1">
                                <a class="btn-outline btn-sm" href="{{ route('employee.overtime-attachment.show', $row) }}">View</a>
                                @can('update', $row)
                                    <a class="btn-outline btn-sm" href="{{ route('employee.overtime-attachment.edit', $row) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No attachment reports" message="When you have recorded overtime on your DTR, create a report from the list above." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())<div class="card-footer">{{ $reports->links() }}</div>@endif
    </div>
</div>
@endsection
