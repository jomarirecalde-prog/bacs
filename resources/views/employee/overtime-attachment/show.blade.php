@extends('layouts.app')
@section('title', $report->report_no)
@section('page-title', $report->report_no)
@section('page-subtitle', 'OT Attachment Report')

@section('content')
@include('overtime-attachment.partials.details', ['report' => $report, 'canPdf' => $canPdf ?? false])

<div class="mt-4 flex flex-wrap gap-2">
    <a href="{{ route('employee.overtime-attachment.index') }}" class="btn-outline">Back to list</a>
    @if ($canEdit)
        <a href="{{ route('employee.overtime-attachment.edit', $report) }}" class="btn btn-primary">Edit</a>
    @endif
    @if ($canPdf)
        <a href="{{ route('employee.overtime-attachment.pdf', $report) }}" class="btn-outline">Download PDF</a>
    @endif
    @can('cancel', $report)
        <form method="POST" action="{{ route('employee.overtime-attachment.cancel', $report) }}" onsubmit="return confirm('Cancel this report?')">@csrf
            <button type="submit" class="btn-outline text-critical">Cancel</button>
        </form>
    @endcan
</div>
@endsection
