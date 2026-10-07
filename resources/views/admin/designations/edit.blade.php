@extends('layouts.app')

@section('title', 'Edit designation')
@section('page-title', 'Edit designation')
@section('page-subtitle', $designation->designation_name)

@section('content')
<a href="{{ route('admin.designations.show', $designation) }}" class="btn-outline btn-sm mb-4">← Back to designation</a>

<form method="POST" action="{{ route('admin.designations.update', $designation) }}" class="space-y-4">
    @csrf
    @method('PUT')
    @include('admin.designations.partials.form', ['designation' => $designation])
    <div class="flex gap-2">
        <button type="submit" class="btn-primary">Update designation</button>
        <a href="{{ route('admin.designations.show', $designation) }}" class="btn-outline">Cancel</a>
    </div>
</form>
@endsection
