<div class="card">
    <div class="card-header flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="card-title">{{ $report->report_no }}</h2>
            <p class="text-sm text-muted">Coverage: {{ $report->coverageLabel() }} · DTR OT {{ $report->overtimeRequest?->attendance_date?->format('M j, Y') ?? '—' }}</p>
        </div>
        <span class="{{ $report->status->badgeClass() }}">{{ $report->status->label() }}</span>
    </div>
    <div class="card-body grid gap-6 lg:grid-cols-2">
        <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-muted">Employee</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">Name</dt><dd class="font-semibold text-ink">{{ $report->employee?->fullName() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Position</dt><dd>{{ $report->employee?->position ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Department</dt><dd>{{ $report->employee?->department?->name ?? '—' }}</dd></div>
            </dl>
        </div>
        <div>
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-muted">Report</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-4"><dt class="text-muted">Date submitted</dt><dd>{{ $report->date_submitted?->format('M j, Y') ?? $report->submittedLabel() ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Total hours</dt><dd class="font-semibold">{{ $report->durationLabel() }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Prepared by</dt><dd>{{ $report->prepared_by ?? '—' }}</dd></div>
                <div class="flex justify-between gap-4"><dt class="text-muted">Approved by</dt><dd>{{ $report->approved_by ?? '—' }}</dd></div>
                @if ($report->review_notes)
                    <div><dt class="text-muted">Review notes</dt><dd class="mt-1">{{ $report->review_notes }}</dd></div>
                @endif
                @if ($canPdf ?? false)
                    <div class="flex flex-wrap gap-2 pt-2">
                        <a class="btn-outline btn-sm" href="{{ route('employee.overtime-attachment.pdf', $report) }}">Download PDF</a>
                        <a class="btn-outline btn-sm" href="{{ route('employee.overtime-attachment.preview', $report) }}" target="_blank" rel="noopener">Preview</a>
                    </div>
                @endif
            </dl>
        </div>
    </div>
</div>

@include('overtime-attachment.partials.activities-table', ['report' => $report])

@if ($report->actions->isNotEmpty())
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Approval History</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Status</th><th>Remarks</th></tr></thead>
                <tbody>
                    @foreach ($report->actions as $action)
                        <tr>
                            <td>{{ $action->acted_at?->format('M j, Y g:i A') }}</td>
                            <td>{{ $action->user?->name }}</td>
                            <td>{{ ucfirst($action->action) }}</td>
                            <td>{{ $action->new_status?->label() ?? '—' }}</td>
                            <td>{{ $action->reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
