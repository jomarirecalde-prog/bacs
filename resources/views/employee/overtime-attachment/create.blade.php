@extends('layouts.app')
@section('title', 'New OT Attachment Report')
@section('page-title', 'New OT Attachment Report')
@section('page-subtitle', 'Document OT activities for approval')

@section('content')
<form method="POST" action="{{ route('employee.overtime-attachment.store') }}" class="card">
    @csrf
    <div class="card-header"><h2 class="card-title">Report details</h2></div>
    <div class="card-body">
        @include('overtime-attachment.partials.form', [
            'employee' => $employee,
            'preselectedOvertimeRequestId' => $preselectedOvertimeRequestId,
            'linkedOvertimeRequest' => $linkedOvertimeRequest,
        ])
    </div>
</form>
@endsection
