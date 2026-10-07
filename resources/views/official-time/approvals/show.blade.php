@extends('layouts.app')
@section('title', $ot->request_no)
@section('page-title', 'Review Official Time')

@section('content')
@include('official-time.partials.details', ['ot' => $ot, 'canDownload' => $canDownload])

@if ($canAct)
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Approval action</h2></div>
        <form method="POST" action="{{ route('official-time.approvals.decide', $ot) }}" class="card-body space-y-3">@csrf
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" required> Endorse / Approve</label>
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied"> Reject</label>
            </div>
            <textarea name="reason" class="textarea" rows="2" placeholder="Remarks (required if rejecting)"></textarea>
            <button class="btn btn-primary" onclick="return confirm('Confirm this action?')">Submit decision</button>
        </form>
    </div>
    <div class="card mt-4">
        <div class="card-header"><h2 class="card-title">Return for revision</h2></div>
        <form method="POST" action="{{ route('official-time.approvals.return', $ot) }}" class="card-body space-y-3">@csrf
            <textarea name="reason" class="textarea" rows="2" placeholder="Revision notes (required)" required></textarea>
            <button class="btn-outline">Return to employee</button>
        </form>
    </div>
@endif
@endsection
