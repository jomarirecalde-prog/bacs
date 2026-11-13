@extends('layouts.app')
@section('title', 'New Official Business')
@section('page-title', 'Official Business Request')
@section('page-subtitle', 'File an official business request')

@section('content')
<form method="POST" action="{{ route('employee.official-business.store') }}" class="space-y-6">
    @csrf
    @include('official-business.partials.form-fields', [
        'employee' => $employee,
        'transportOptions' => $transportOptions,
        'searchUrl' => $searchUrl,
        'selectedParticipants' => [],
        'externalParticipants' => [''],
    ])
    <div class="flex flex-wrap gap-3">
        <button type="submit" name="action" value="draft" class="btn btn-secondary">Save draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-primary">Submit request</button>
    </div>
</form>
@endsection
