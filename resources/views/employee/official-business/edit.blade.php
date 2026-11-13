@extends('layouts.app')
@section('title', 'Edit Official Business')
@section('page-title', 'Edit Official Business')
@section('page-subtitle', $obRequest->reference_number)

@section('content')
<form method="POST" action="{{ route('employee.official-business.update', $obRequest) }}" class="space-y-6">
    @csrf
    @method('PUT')
    @include('official-business.partials.form-fields', [
        'employee' => $employee,
        'obRequest' => $obRequest,
        'transportOptions' => $transportOptions,
        'searchUrl' => $searchUrl,
        'selectedParticipants' => $selectedParticipants,
        'externalParticipants' => $externalParticipants !== [] ? $externalParticipants : [''],
    ])
    <div class="flex flex-wrap gap-3">
        <button type="submit" name="action" value="draft" class="btn btn-secondary">Save draft</button>
        <button type="submit" name="action" value="submit" class="btn btn-primary">Submit request</button>
    </div>
</form>
@endsection
