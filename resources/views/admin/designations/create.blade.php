@extends('layouts.app')

@section('title', 'Add designation')
@section('page-title', 'Add designation')
@section('page-subtitle', 'Enter position details and default compensation manually')

@section('content')
<a href="{{ route('admin.designations.index') }}" class="btn-outline btn-sm mb-4">← All designations</a>

<form method="POST" action="{{ route('admin.designations.store') }}" class="space-y-4">
    @csrf
    @include('admin.designations.partials.form')
    <div class="flex gap-2">
        <button type="submit" class="btn-primary">Save designation</button>
        <a href="{{ route('admin.designations.index') }}" class="btn-outline">Cancel</a>
    </div>
</form>
@endsection
