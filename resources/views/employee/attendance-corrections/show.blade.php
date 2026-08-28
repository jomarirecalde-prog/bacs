@extends('layouts.app')

@section('title', 'Correction Request')
@section('page-title', 'DTR Correction Request')
@section('page-subtitle', $correction->attendance_date->toFormattedDateString().' · '.$correction->punchLabel())

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card card-accent-brand h-fit overflow-hidden lg:col-span-2">
        <div class="card-header">
            <h2 class="card-title">Request details</h2>
            <span class="badge-{{ match($correction->status->color()) { 'yellow' => 'warn', 'green' => 'brand', 'red' => 'critical', default => 'neutral' } }}">{{ $correction->status->label() }}</span>
        </div>
        <dl class="divide-y divide-line px-5 text-sm">
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Date</dt>
                <dd class="font-semibold text-ink">{{ $correction->attendance_date->toFormattedDateString() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Field</dt>
                <dd class="font-semibold text-ink">{{ $correction->punchLabel() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Original value</dt>
                <dd class="tabular-nums text-muted">{{ $correction->formattedOriginal() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-3">
                <dt class="text-muted">Requested value</dt>
                <dd class="tabular-nums font-bold text-brand-700">{{ $correction->formattedRequested() }}</dd>
            </div>
            <div class="py-3">
                <dt class="text-muted">Reason</dt>
                <dd class="mt-1 text-ink">{{ $correction->reason }}</dd>
            </div>
            @if ($correction->review_notes)
                <div class="py-3">
                    <dt class="text-muted">Admin notes</dt>
                    <dd class="mt-1 text-ink">{{ $correction->review_notes }}</dd>
                </div>
            @endif
            @if ($correction->reviewer)
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-muted">Reviewed by</dt>
                    <dd>{{ $correction->reviewer->name }} · {{ $correction->reviewed_at?->format('M d, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>
        <div class="card-footer flex flex-wrap gap-2">
            @if ($correction->status->isOpen())
                <form method="POST" action="{{ route('employee.attendance-corrections.cancel', $correction) }}" onsubmit="return confirm('Cancel this correction request?')">
                    @csrf
                    <button type="submit" class="btn-outline">Cancel request</button>
                </form>
            @endif
            <a href="{{ route('employee.attendance-corrections.index') }}" class="btn-outline">Back to list</a>
            @if ($correction->attendance_id)
                <a href="{{ route('employee.dtr') }}" class="btn-primary">View my DTR</a>
            @endif
        </div>
    </div>

    <div class="card h-fit overflow-hidden">
        <div class="card-header"><h2 class="card-title">What happens next</h2></div>
        <div class="space-y-3 p-5 text-sm text-muted">
            <p>Your request is reviewed by an administrator. If approved, only the <strong class="text-ink">{{ $correction->punchLabel() }}</strong> field for this date is updated.</p>
            <p>While a correction is pending for today, QR station scans are temporarily blocked until the request is resolved.</p>
        </div>
    </div>
</div>
@endsection
