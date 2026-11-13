@extends('layouts.app')
@section('title', 'Edit OT Attachment Report')
@section('page-title', 'Edit '.$report->report_no)
@section('page-subtitle', 'Update draft or returned report')

@section('content')
<form method="POST" action="{{ route('employee.overtime-attachment.update', $report) }}" class="card">
    @csrf
    @method('PUT')
    <div class="card-header"><h2 class="card-title">{{ $report->report_no }}</h2></div>
    <div class="card-body">
        @include('overtime-attachment.partials.form', ['employee' => $employee, 'report' => $report])
    </div>
</form>
@endsection
