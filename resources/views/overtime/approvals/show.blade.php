@extends('layouts.app')

@section('title', 'Review Overtime')
@section('page-title', 'Overtime request')
@section('page-subtitle', $overtimeRequest->employee?->fullName().' · '.$overtimeRequest->attendance_date?->format('M j, Y'))

@section('content')
<div class="space-y-6">
    <a href="{{ route('overtime.approvals.index') }}" class="btn-outline btn-sm">Back to pending requests</a>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Overtime details</h2>
                <span class="badge-warn">{{ $overtimeRequest->status?->label() }}</span>
            </div>
            <dl class="divide-y divide-line px-5 text-sm">
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-muted">Recorded OT</dt>
                    <dd class="tabular-nums font-semibold">{{ number_format($overtimeRequest->recorded_minutes / 60, 2) }} hours</dd>
                </div>
                <div class="flex justify-between gap-4 py-3">
                    <dt class="text-muted">Approved OT</dt>
                    <dd class="tabular-nums">{{ number_format($overtimeRequest->approved_minutes / 60, 2) }} hours</dd>
                </div>
            </dl>
        </div>

        @if ($canApprove)
            <div class="card overflow-hidden">
                <div class="card-header"><h3 class="card-title">Your decision</h3></div>
                <form method="POST" action="{{ route('overtime.approvals.decide', $overtimeRequest) }}" class="space-y-4 p-5" x-data="{ decision: 'approved' }">
                    @csrf
                    <label class="flex items-center gap-2">
                        <input type="radio" name="decision" value="approved" class="radio" x-model="decision">
                        <span class="font-semibold text-brand-800">Approve</span>
                    </label>
                    <label class="flex items-center gap-2">
                        <input type="radio" name="decision" value="denied" class="radio" x-model="decision">
                        <span class="font-semibold text-critical-700">Deny</span>
                    </label>
                    <div>
                        <label class="label">Approved minutes (optional)</label>
                        <input type="number" name="approved_minutes" class="input" min="0" max="{{ $overtimeRequest->recorded_minutes }}" value="{{ $overtimeRequest->recorded_minutes }}">
                    </div>
                    <div>
                        <label class="label">Notes / reason</label>
                        <textarea name="reason" class="textarea" rows="3"></textarea>
                    </div>
                    <button type="submit" class="btn-primary btn-block">Submit decision</button>
                </form>
            </div>
        @endif
    </div>
</div>
@endsection
