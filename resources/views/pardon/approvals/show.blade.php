@extends('layouts.app')
@section('title', 'Review Pardon Request')
@section('page-title', 'Pardon / Time Correction Review')

@section('content')
<div class="card">
    <div class="card-body space-y-3 text-sm">
        <div><span class="text-muted">Employee</span><div class="font-semibold">{{ $correction->employee?->fullName() }}</div></div>
        <div><span class="text-muted">Date</span><div>{{ $correction->attendance_date?->format('M j, Y') }}</div></div>
        <div><span class="text-muted">Correction</span><div>{{ $correction->punchLabel() }}</div></div>
        <div><span class="text-muted">Reason</span><div>{{ $correction->reason }}</div></div>
    </div>
</div>
<form method="POST" action="{{ route('pardon.approvals.decide', $correction) }}" class="card mt-6">
    @csrf
    <div class="card-body space-y-3">
        <div class="flex gap-4">
            <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" required> Endorse</label>
            <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied"> Reject</label>
        </div>
        <textarea name="reason" class="textarea" rows="2" placeholder="Remarks (required if rejecting)"></textarea>
        <button class="btn-primary">Submit</button>
    </div>
</form>
@endsection
