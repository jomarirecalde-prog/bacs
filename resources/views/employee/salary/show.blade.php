@extends('layouts.app')

@section('title', 'Salary details')
@section('page-title', 'Salary details')
@section('page-subtitle', $payrollEmployee->payrollPeriod?->period_name ?? 'Payroll period')

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('employee.salary.index') }}" class="btn-outline btn-sm">← My Salary</a>
    @can('downloadPayslip', $payrollEmployee)
        <a href="{{ route('employee.payroll.pdf', $payrollEmployee) }}" class="btn-primary btn-sm">Download payslip PDF</a>
        <a href="{{ route('employee.payroll.print', $payrollEmployee) }}" class="btn-outline btn-sm" target="_blank">Print</a>
    @endcan
</div>

<div class="card mb-6 overflow-hidden">
    <div class="flex flex-wrap items-start justify-between gap-4 p-6">
        <div>
            <div class="text-sm text-muted">{{ $payrollEmployee->employee_name }} · {{ $payrollEmployee->employee_number }}</div>
            <div class="mt-1 text-sm">
                {{ $payrollEmployee->department_name ?? '—' }}
                · {{ $payrollEmployee->designation_name ?? '—' }}
            </div>
            @if ($payrollEmployee->payrollPeriod)
                <div class="mt-3 text-sm">
                    {{ $payrollEmployee->payrollPeriod->start_date->format('M j, Y') }}
                    – {{ $payrollEmployee->payrollPeriod->end_date->format('M j, Y') }}
                    @if ($payrollEmployee->payrollPeriod->payroll_date)
                        · Payment date: {{ $payrollEmployee->payrollPeriod->payroll_date->format('M j, Y') }}
                    @endif
                </div>
            @endif
            <div class="mt-2 flex flex-wrap items-center gap-2">
                <span class="badge-neutral">{{ $payrollEmployee->payrollPeriod?->status?->employeeSalaryLabel() }}</span>
                @include('employee.salary.partials.amount-kind', ['amountKind' => $amountKind, 'showHint' => true])
            </div>
        </div>
        <div class="text-right">
            @php
                $netLabel = match ($amountKind) {
                    'released' => 'Net salary (released)',
                    'approved' => 'Net salary (approved)',
                    default => 'Estimated net salary',
                };
            @endphp
            <div class="text-xs font-bold uppercase tracking-wide text-muted">{{ $netLabel }}</div>
            <div class="text-3xl font-extrabold tabular-nums text-brand-700">₱{{ number_format($payrollEmployee->net_pay, 2) }}</div>
        </div>
    </div>
</div>

@if ($payrollEmployee->computation_warnings)
    <div class="alert-warning mb-4 text-sm">{{ implode(' · ', $payrollEmployee->computation_warnings) }}</div>
@endif

@include('employee.salary.partials.breakdown', ['payrollEmployee' => $payrollEmployee])
@endsection
