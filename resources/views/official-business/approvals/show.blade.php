@extends('layouts.app')
@section('title', $obRequest->reference_number)
@section('page-title', 'Review Official Business')

@section('content')
@include('official-business.partials.details', ['obRequest' => $obRequest])
@if ($canEndorse)
    <div class="card mt-6">
        <div class="card-header"><h2 class="card-title">Endorsement action</h2></div>
        <form method="POST" action="{{ route('official-business.approvals.decide', $obRequest) }}" class="card-body space-y-3">@csrf
            <div class="flex gap-4">
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" required> Endorse</label>
                <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied"> Reject</label>
            </div>
            <textarea name="reason" class="textarea" rows="2" placeholder="Remarks (required if rejecting)"></textarea>
            <button class="btn btn-primary">Submit</button>
        </form>
    </div>
@endif
@endsection
