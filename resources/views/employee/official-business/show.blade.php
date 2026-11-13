@extends('layouts.app')
@section('title', $obRequest->reference_number)
@section('page-title', 'Official Business Details')
@section('page-subtitle', $obRequest->reference_number)

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    @if ($canEdit)<a href="{{ route('employee.official-business.edit', $obRequest) }}" class="btn btn-secondary">Edit</a>@endif
    @if ($canDownload)
        <a href="{{ route('employee.official-business.pdf', $obRequest) }}" class="btn btn-primary">Download PDF</a>
        <a href="{{ route('employee.official-business.preview', $obRequest) }}" class="btn btn-secondary" target="_blank" rel="noopener">Preview</a>
        <a href="{{ route('employee.official-business.print', $obRequest) }}" class="btn btn-secondary" target="_blank" rel="noopener">Print</a>
    @endif
    @if ($canCancel)
        <form method="POST" action="{{ route('employee.official-business.cancel', $obRequest) }}" onsubmit="return confirm('Cancel this official business request?')">@csrf<button class="btn btn-critical">Cancel request</button></form>
    @endif
</div>

@include('official-business.partials.details', ['obRequest' => $obRequest])

@if ($canEndorse)
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Your endorsement</h2></div>
        <form method="POST" action="{{ route('official-business.approvals.decide', $obRequest) }}" class="card-body space-y-3">@csrf
            <div class="flex gap-3">
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" required> Endorse</label>
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied"> Reject</label>
            </div>
            <textarea name="reason" class="textarea" rows="2" placeholder="Remarks (required if rejecting)"></textarea>
            <button class="btn btn-primary">Submit decision</button>
        </form>
    </div>
@endif
@endsection
