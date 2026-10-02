@extends('layouts.app')

@section('title', 'Review Pardon Request')
@section('page-title', 'Pardon / Time Correction')
@section('page-subtitle', $correction->employee?->fullName().' · '.$correction->attendance_date?->format('M j, Y'))

@section('content')
<div class="space-y-6">
    <a href="{{ route('pardon.approvals.index') }}" class="btn-outline btn-sm">Back to pending requests</a>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card card-accent-brand xl:col-span-2 overflow-hidden">
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
                    <dt class="text-muted">Date</dt>
                    <dd>{{ $correction->attendance_date?->format('F j, Y') }}</dd>
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
                    <dt class="text-muted">Reason</dt>
                    <dd class="mt-1 text-ink">{{ $correction->reason }}</dd>
                </div>
            </dl>
        </div>

        <div class="space-y-4">
            <div class="card card-accent-gold overflow-hidden">
                <div class="card-header"><h3 class="card-title">Approval progress</h3></div>
                <div class="card-body">@include('pardon.partials.timeline', ['correction' => $correction])</div>
            </div>

            @if ($canApprove)
                @php $isFinal = $correction->current_stage === \App\Enums\LeaveApprovalStage::CeoFinalApproval; @endphp
                <div class="card card-accent-brand" x-data="{ decision: 'approved' }">
                    <div class="card-header">
                        <h3 class="card-title">{{ $isFinal ? 'Final decision' : 'Your decision' }}</h3>
                        @if ($isFinal)
                            <p class="text-xs font-semibold text-gold-700">FINAL APPROVAL</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('pardon.approvals.decide', $correction) }}" class="card-body space-y-4">
                        @csrf
                        <label class="flex items-center gap-2">
                            <input type="radio" name="decision" value="approved" class="radio" x-model="decision">
                            <span class="font-semibold text-brand-800">{{ $isFinal ? 'Final approve' : 'Endorse / approve' }}</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="decision" value="denied" class="radio" x-model="decision">
                            <span class="font-semibold text-critical-700">Reject</span>
                        </label>
                        <div>
                            <label class="label" for="reason">Remarks <span x-show="decision === 'denied'" class="text-critical-600">*</span></label>
                            <textarea id="reason" name="reason" rows="3" class="textarea" :required="decision === 'denied'"></textarea>
                        </div>
                        <button class="btn-primary btn-block" type="submit">Record decision</button>
                    </form>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
