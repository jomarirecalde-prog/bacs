@extends('layouts.app')
@section('title', $obRequest->reference_number)
@section('page-title', 'Official Business — Admin')
@section('page-subtitle', $obRequest->reference_number)

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    @if ($canDownload)
        <a href="{{ route('admin.official-business.pdf', $obRequest) }}" class="btn btn-secondary">Download PDF</a>
        <a href="{{ route('admin.official-business.preview', $obRequest) }}" class="btn btn-secondary" target="_blank" rel="noopener">Preview</a>
    @endif
</div>

@include('official-business.partials.details', ['obRequest' => $obRequest])
@endsection
