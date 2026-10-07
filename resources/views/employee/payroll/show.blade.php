@extends('layouts.app')

@section('title', 'Payslip')
@section('page-title', 'My payslip')
@section('page-subtitle', $payrollEmployee->payrollPeriod->period_name)

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('employee.payroll.index') }}" class="btn-outline btn-sm">← Payroll history</a>
    <a href="{{ route('employee.payroll.pdf', $payrollEmployee) }}" class="btn-primary btn-sm">Download PDF</a>
    <a href="{{ route('employee.payroll.print', $payrollEmployee) }}" class="btn-outline btn-sm" target="_blank">Print</a>
</div>

@include('admin.payroll.employees.show', ['payrollEmployee' => $payrollEmployee])
@endsection
