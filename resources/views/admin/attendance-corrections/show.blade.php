@extends('layouts.app')

@section('title', 'Review Correction')
@section('page-title', 'Review DTR Correction')
@section('page-subtitle', $correction->employee?->fullName().' · '.$correction->attendance_date->toFormattedDateString())

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card h-fit overflow-hidden lg:col-span-2">
        <div class="card-header">
            <h2 class="card-title">Correction request</h2>
            <span class="badge-{{ match($correction->status->color()) { 'yellow' => 'warn', 'green' => 'brand', 'red' => 'critical', default => 'neutral' } }}">{{ $correction->status->label() }}</span>
        </div>
        <dl class="divide-y divide-line px-5 text-sm">
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Employee</dt>
                <dd class="font-semibold text-ink">{{ $correction->employee?->fullName() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Department</dt>
                <dd>{{ $correction->employee?->department?->name ?? '—' }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Date</dt>
                <dd>{{ $correction->attendance_date->toFormattedDateString() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Field</dt>
                <dd class="font-bold text-ink">{{ $correction->punchLabel() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Original</dt>
                <dd class="tabular-nums text-muted">{{ $correction->formattedOriginal() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Requested</dt>
                <dd class="tabular-nums font-bold text-brand-700">{{ $correction->formattedRequested() }}</dd>
            </div>
            <div class="py-3">
                <dt class="text-muted">Employee reason</dt>
                <dd class="mt-1 text-ink">{{ $correction->reason }}</dd>
            </div>
        </dl>

        @if ($correction->status->isOpen() && auth()->user()->canEditDtr())
            <div class="border-t border-line p-5">
                <form method="POST" action="{{ route('admin.attendance-corrections.review', $correction) }}" class="space-y-4">
                    @csrf
                    <div>
                        <label class="label" for="review_notes">Admin notes (optional)</label>
                        <textarea id="review_notes" class="textarea" name="review_notes" rows="2">{{ old('review_notes') }}</textarea>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" name="decision" value="approve" class="btn-primary">Approve &amp; update DTR</button>
                        <button type="submit" name="decision" value="reject" class="btn-outline">Reject</button>
                    </div>
                </form>
            </div>
        @elseif ($correction->review_notes || $correction->reviewer)
            <div class="card-footer text-sm text-muted">
                Reviewed by {{ $correction->reviewer?->name ?? '—' }}
                @if ($correction->reviewed_at)
                    on {{ $correction->reviewed_at->format('M d, Y g:i A') }}
                @endif
                @if ($correction->review_notes)
                    <div class="mt-2 text-ink">{{ $correction->review_notes }}</div>
                @endif
            </div>
        @endif
    </div>

    <div class="space-y-4">
        @if ($correction->attendance)
            <div class="card overflow-hidden">
                <div class="card-header"><h2 class="card-title">Current DTR record</h2></div>
                <dl class="divide-y divide-line px-5 text-sm">
                    @foreach ([
                        'AM Time In' => $correction->attendance->am_time_in,
                        'AM Time Out' => $correction->attendance->am_time_out,
                        'PM Time In' => $correction->attendance->pm_time_in,
                        'PM Time Out' => $correction->attendance->pm_time_out,
                        'Overtime' => $correction->attendance->overtime_in,
                    ] as $label => $value)
                        <div class="flex justify-between gap-4 py-2.5">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="tabular-nums font-medium {{ $correction->punchLabel() === $label ? 'text-brand-700 font-bold' : 'text-ink' }}">{{ $value?->format('h:i A') ?? '—' }}</dd>
                        </div>
                    @endforeach
                </dl>
                <div class="card-footer">
                    <a href="{{ route('admin.dtr.show', $correction->attendance) }}" class="btn-outline btn-sm btn-block">Open full DTR record</a>
                </div>
            </div>
        @endif

        <div class="alert-info">
            <span class="text-xs">Approving updates only the requested field and records the change in the DTR audit trail.</span>
        </div>
    </div>
</div>
@endsection
