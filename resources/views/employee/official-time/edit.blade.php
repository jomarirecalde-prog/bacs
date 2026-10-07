@extends('layouts.app')
@section('title', 'Edit Official Time')
@section('page-title', 'Edit Official Time')

@section('content')
<form method="POST" action="{{ route('employee.official-time.update', $ot) }}" enctype="multipart/form-data" class="card">
    @csrf @method('PUT')
    <div class="card-body space-y-4">
        @include('official-time.partials.form', ['employee' => $employee, 'types' => $types, 'ot' => $ot])
        <div class="flex flex-wrap gap-3">
            <button type="submit" name="action" value="draft" class="btn-outline">Save draft</button>
            <button type="submit" name="action" value="submit" class="btn btn-primary" onclick="return confirm('Resubmit this Official Time request?')">Submit</button>
        </div>
    </div>
</form>
@endsection
