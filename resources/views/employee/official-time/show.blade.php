@extends('layouts.app')
@section('title', $ot->request_no)
@section('page-title', 'Official Time Details')

@section('content')
@include('official-time.partials.details', ['ot' => $ot, 'canDownload' => $canDownload])

<div class="mt-4 flex flex-wrap gap-2">
    <a href="{{ route('employee.official-time.index') }}" class="btn-outline">Back to list</a>
    @if ($canEdit)
        <a href="{{ route('employee.official-time.edit', $ot) }}" class="btn btn-primary">Edit</a>
    @endif
    @if ($canCancel)
        <form method="POST" action="{{ route('employee.official-time.cancel', $ot) }}" onsubmit="return confirm('Cancel this Official Time request?')">@csrf
            <button type="submit" class="btn-outline-danger">Cancel</button>
        </form>
    @endif
</div>
@endsection
