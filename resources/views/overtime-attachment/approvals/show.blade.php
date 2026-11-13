@extends('layouts.app')
@section('title', $report->report_no)
@section('page-title', 'Review '.$report->report_no)
@section('page-subtitle', 'OT Attachment Report approval')

@section('content')
@include('overtime-attachment.partials.details', ['report' => $report, 'canPdf' => $canPdf ?? false])

@if ($canAct)
    <div class="mt-6 grid gap-4 lg:grid-cols-2">
        <form method="POST" action="{{ route('overtime-attachment.approvals.decide', $report) }}" class="card">@csrf
            <div class="card-header"><h2 class="card-title">Decision</h2></div>
            <div class="card-body space-y-3">
                <div class="flex flex-wrap gap-4">
                    <label class="flex items-center gap-2"><input type="radio" name="decision" value="approved" required> Endorse / Approve</label>
                    <label class="flex items-center gap-2"><input type="radio" name="decision" value="denied"> Reject</label>
                </div>
                <textarea name="reason" class="textarea" rows="2" placeholder="Remarks (required if rejecting)"></textarea>
                <button type="submit" class="btn btn-primary">Submit decision</button>
            </div>
        </form>
        <form method="POST" action="{{ route('overtime-attachment.approvals.return', $report) }}" class="card">@csrf
            <div class="card-header"><h2 class="card-title">Return for revision</h2></div>
            <div class="card-body space-y-3">
                <textarea name="reason" class="input" rows="4" placeholder="Revision notes (required)" required></textarea>
                <button type="submit" class="btn-outline w-full">Return to employee</button>
            </div>
        </form>
    </div>
@endif

<a href="{{ route('overtime-attachment.approvals.index') }}" class="btn-outline mt-4 inline-flex">Back</a>
@endsection
