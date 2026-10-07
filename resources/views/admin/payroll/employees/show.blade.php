@extends('layouts.app')

@section('title', 'Payroll computation')
@section('page-title', $payrollEmployee->employee_name)
@section('page-subtitle', 'Payroll computation · '.$payrollEmployee->payrollPeriod->period_name)

@section('content')
<div class="mb-4 flex flex-wrap gap-2">
    <a href="{{ route('admin.payroll.register.index', $payrollEmployee->payrollPeriod) }}" class="btn-outline btn-sm">← Register</a>
    <a href="{{ route('admin.payroll.employees.payroll.pdf', $payrollEmployee) }}" class="btn-primary btn-sm">Download payslip PDF</a>
    <a href="{{ route('admin.payroll.employees.payroll.print', $payrollEmployee) }}" class="btn-outline btn-sm" target="_blank">Print payslip</a>
</div>

@if (session('success'))
    <div class="alert-success mb-4 text-sm">{{ session('success') }}</div>
@endif

@if ($payrollEmployee->computation_warnings)
    <div class="alert-warning mb-4 text-sm">{{ implode(' · ', $payrollEmployee->computation_warnings) }}</div>
@endif

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Snapshot</h2></div>
        <dl class="divide-y divide-line p-5 text-sm">
            @foreach ([
                ['Employee #', $payrollEmployee->employee_number],
                ['Designation', $payrollEmployee->designation_name ?? '—'],
                ['Department', $payrollEmployee->department_name ?? '—'],
                ['Salary type', $payrollEmployee->salary_type?->label() ?? '—'],
                ['Monthly salary', $payrollEmployee->monthly_salary ? '₱'.number_format($payrollEmployee->monthly_salary, 2) : '—'],
                ['Daily rate', $payrollEmployee->daily_rate ? '₱'.number_format($payrollEmployee->daily_rate, 2) : '—'],
                ['Hourly rate', $payrollEmployee->hourly_rate ? '₱'.number_format($payrollEmployee->hourly_rate, 4) : '—'],
            ] as [$label, $value])
                <div class="flex justify-between gap-4 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="font-semibold text-ink">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Totals</h2></div>
        <dl class="divide-y divide-line p-5 text-sm">
            @foreach ([
                ['Basic Pay', $payrollEmployee->basic_pay],
                ['Less: Absence', -$payrollEmployee->absence_deduction],
                ['Less: Late', -$payrollEmployee->late_deduction],
                ['Less: Undertime', -$payrollEmployee->undertime_deduction],
                ['Suggested total basic (reference)', $payrollEmployee->computed_total_basic_pay],
                ['Total Basic Pay (entered)', $payrollEmployee->total_basic_pay],
                ['Overtime', $payrollEmployee->overtime_pay],
                ['Gross Wage', $payrollEmployee->gross_wage],
                ['De Minimis', $payrollEmployee->de_minimis],
                ['Gross Compensation', $payrollEmployee->gross_compensation],
                ['Total Deductions (incl. ALU display)', $payrollEmployee->total_deductions],
                ['NET PAY', $payrollEmployee->net_pay],
            ] as [$label, $amount])
                <div class="flex justify-between gap-4 py-2 {{ $label === 'NET PAY' ? 'text-base font-bold text-brand-700' : '' }}">
                    <dt class="{{ $label === 'NET PAY' ? '' : 'text-muted' }}">{{ $label }}</dt>
                    <dd class="tabular-nums {{ $amount < 0 ? 'text-critical-700' : 'font-semibold text-ink' }}">
                        {{ $amount < 0 ? '−' : '' }}₱{{ number_format(abs($amount), 2) }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Earnings lines</h2></div>
        <ul class="divide-y divide-line p-5 text-sm">
            @forelse ($payrollEmployee->earnings as $line)
                <li class="flex justify-between py-2">
                    <span>{{ $line->label }}</span>
                    <span class="tabular-nums font-semibold">₱{{ number_format($line->amount, 2) }}</span>
                </li>
            @empty
                <li class="text-muted">No earning lines.</li>
            @endforelse
        </ul>
    </div>
    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Deduction lines</h2></div>
        <ul class="divide-y divide-line p-5 text-sm">
            @forelse ($payrollEmployee->deductions as $line)
                <li class="flex justify-between py-2">
                    <span>{{ $line->label }}</span>
                    <span class="tabular-nums font-semibold text-critical-700">−₱{{ number_format($line->amount, 2) }}</span>
                </li>
            @empty
                <li class="text-muted">No deduction lines.</li>
            @endforelse
        </ul>
    </div>
</div>
@endsection
