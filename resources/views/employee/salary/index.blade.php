@extends('layouts.app')

@section('title', 'My Salary')
@section('page-title', 'My Salary')
@section('page-subtitle', 'Current pay period, earnings breakdown, and payroll history')

@section('content')
<div class="space-y-6">
    <div class="card card-accent-brand overflow-hidden">
        <div class="flex flex-wrap items-start justify-between gap-4 p-6">
            <div class="min-w-0 flex-1">
                <div class="stat-label">Employee</div>
                <div class="mt-1 text-xl font-extrabold text-ink">{{ $employee->fullName() }}</div>
                <div class="mt-1 text-sm text-muted">
                    {{ $employee->employee_number }}
                    · {{ $employee->department?->name ?? '—' }}
                    · {{ $employee->designation?->designation_name ?? $employee->position ?? '—' }}
                </div>
                @if ($period)
                    <div class="mt-4 text-sm">
                        <span class="font-semibold text-ink">Payroll period:</span>
                        {{ $period->period_name }}
                        <span class="text-muted">({{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }})</span>
                    </div>
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                        <span class="text-muted">Status:</span>
                        <span class="badge-neutral">{{ $period->status?->employeeSalaryLabel() }}</span>
                        @include('employee.salary.partials.amount-kind', ['amountKind' => $amountKind, 'showHint' => false])
                    </div>
                @else
                    <p class="mt-4 text-sm text-muted">No active payroll period is configured.</p>
                @endif
            </div>
            <div class="rounded-2xl border border-brand-200 bg-brand-50/60 p-5 text-right min-w-[12rem]">
                @if ($payrollEmployee)
                    @php
                        $netLabel = match ($amountKind) {
                            'released' => 'Net salary (released)',
                            'approved' => 'Net salary (approved)',
                            default => 'Estimated net salary',
                        };
                    @endphp
                    <div class="text-xs font-bold uppercase tracking-wide text-brand-800">{{ $netLabel }}</div>
                    <div class="mt-2 text-3xl font-extrabold tabular-nums text-brand-700">₱{{ number_format($payrollEmployee->net_pay, 2) }}</div>
                    @include('employee.salary.partials.amount-kind', ['amountKind' => $amountKind, 'showHint' => true])
                @elseif ($salaryConfig)
                    <div class="text-xs font-bold uppercase tracking-wide text-brand-800">Approved salary rate</div>
                    <div class="mt-2 text-2xl font-extrabold tabular-nums text-brand-700">
                        @if ($salaryConfig->semi_monthly_salary)
                            ₱{{ number_format($salaryConfig->semi_monthly_salary, 2) }}
                            <span class="block text-xs font-semibold text-muted">semi-monthly</span>
                        @elseif ($salaryConfig->monthly_salary)
                            ₱{{ number_format($salaryConfig->monthly_salary, 2) }}
                            <span class="block text-xs font-semibold text-muted">monthly</span>
                        @else
                            —
                        @endif
                    </div>
                    <p class="mt-2 text-xs text-muted">Payroll for this period has not been computed yet. Amounts below will appear after HR runs payroll.</p>
                @else
                    <div class="text-xs font-bold uppercase tracking-wide text-muted">Salary</div>
                    <p class="mt-2 text-sm text-muted">No approved salary configuration on file. Contact HR.</p>
                @endif
            </div>
        </div>
    </div>

    @if ($payrollEmployee)
        @if ($payrollEmployee->computation_warnings)
            <div class="alert-warning text-sm">{{ implode(' · ', $payrollEmployee->computation_warnings) }}</div>
        @endif
        @include('employee.salary.partials.breakdown', ['payrollEmployee' => $payrollEmployee])
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('employee.salary.show', $payrollEmployee) }}" class="btn-outline btn-sm">View period details</a>
            @can('downloadPayslip', $payrollEmployee)
                <a href="{{ route('employee.payroll.pdf', $payrollEmployee) }}" class="btn-primary btn-sm">Download payslip PDF</a>
            @endcan
        </div>
    @elseif ($salaryConfig && $period)
        <div class="card p-6">
            <x-empty-state
                title="Payroll not computed yet"
                message="Your earnings and deductions for {{ $period->period_name }} will appear here once attendance and payroll are processed by HR."
                icon="document"
            />
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="card-header flex flex-wrap items-center justify-between gap-3">
            <h2 class="card-title">Payroll history</h2>
            <a href="{{ route('employee.payroll.index') }}" class="text-sm font-semibold link">Finalized payslips →</a>
        </div>
        <form class="filter-bar border-b border-line px-4 py-3" method="get" action="{{ route('employee.salary.index') }}">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <label class="label text-xs" for="salary-year">Year</label>
                    <select id="salary-year" name="year" class="input">
                        <option value="">All years</option>
                        @foreach ($historyYears as $yr)
                            <option value="{{ $yr }}" @selected((string) ($filters['year'] ?? '') === (string) $yr)>{{ $yr }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label text-xs" for="salary-month">Month</label>
                    <select id="salary-month" name="month" class="input">
                        <option value="">All months</option>
                        @foreach (range(1, 12) as $m)
                            <option value="{{ $m }}" @selected((string) ($filters['month'] ?? '') === (string) $m)>{{ \Carbon\Carbon::create(null, $m, 1)->format('F') }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="label text-xs" for="salary-status">Payroll status</label>
                    <select id="salary-status" name="status" class="input">
                        <option value="">All statuses</option>
                        @foreach (['draft' => 'Draft', 'processing' => 'Processing', 'approved' => 'Approved', 'released' => 'Released'] as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="btn-primary btn-sm">Apply filters</button>
                    <a href="{{ route('employee.salary.index') }}" class="btn-outline btn-sm">Reset</a>
                </div>
            </div>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Period</th>
                        <th class="text-right">Basic pay</th>
                        <th class="text-right">Allowances</th>
                        <th class="text-right">Overtime</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">Deductions</th>
                        <th class="text-right">Net</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $record)
                        @php
                            $periodRow = $record->payrollPeriod;
                            $allowances = (float) $record->de_minimis + (float) $record->other_earnings + (float) $record->holiday_pay + (float) $record->premium_pay;
                            $kind = $periodRow?->status?->employeeAmountKind() ?? 'none';
                        @endphp
                        <tr>
                            <td>
                                <div class="font-semibold text-ink">{{ $periodRow?->period_name ?? '—' }}</div>
                                <div class="text-xs text-muted">
                                    {{ $periodRow?->start_date?->format('M j') }} – {{ $periodRow?->end_date?->format('M j, Y') }}
                                    @if ($periodRow?->payroll_date)
                                        · Paid {{ $periodRow->payroll_date->format('M j, Y') }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-right tabular-nums">₱{{ number_format($record->total_basic_pay, 2) }}</td>
                            <td class="text-right tabular-nums">{{ $allowances > 0 ? '₱'.number_format($allowances, 2) : '—' }}</td>
                            <td class="text-right tabular-nums">{{ $record->overtime_pay > 0 ? '₱'.number_format($record->overtime_pay, 2) : '—' }}</td>
                            <td class="text-right tabular-nums">₱{{ number_format($record->gross_compensation, 2) }}</td>
                            <td class="text-right tabular-nums text-critical-700">−₱{{ number_format($record->total_deductions, 2) }}</td>
                            <td class="text-right tabular-nums font-bold text-brand-700">₱{{ number_format($record->net_pay, 2) }}</td>
                            <td>
                                <span class="badge-neutral">{{ $periodRow?->status?->employeeSalaryLabel() ?? '—' }}</span>
                                @if ($kind === 'estimate')
                                    <span class="ml-1 text-xs text-warn-700">Est.</span>
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap space-x-1">
                                <a class="btn-outline btn-sm" href="{{ route('employee.salary.show', $record) }}">Details</a>
                                @can('downloadPayslip', $record)
                                    <a class="btn-outline btn-sm" href="{{ route('employee.payroll.pdf', $record) }}">PDF</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-0"><x-empty-state title="No payroll records" message="Records appear after payroll is computed for a period." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($history->hasPages())
            <div class="border-t border-line p-4">{{ $history->links() }}</div>
        @endif
    </div>
</div>
@endsection
