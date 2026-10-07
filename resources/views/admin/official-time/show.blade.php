@extends('layouts.app')
@section('title', $ot->request_no)
@section('page-title', 'Official Time Request')

@section('content')
@include('official-time.partials.details', ['ot' => $ot, 'canDownload' => $canDownload])
<a href="{{ route('admin.official-time.index') }}" class="btn-outline mt-4">Back</a>
@endsection
