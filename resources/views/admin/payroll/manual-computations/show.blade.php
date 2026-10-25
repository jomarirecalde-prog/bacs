@extends('layouts.app')

@section('title', 'Manual computation #'.$computation->id)
@section('page-title', 'Manual salary computation')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="page-stack">
    <div class="mb-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.payroll.employees.salary.index', $employee) }}" class="btn-outline btn-sm">← Salary profile</a>
        <a href="{{ route('admin.payroll.employees.manual-computations.print', [$employee, $computation]) }}" class="btn-outline btn-sm" target="_blank">Print / export</a>
    </div>

    @if (session('success'))
        <div class="alert-success text-sm">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert-critical text-sm">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($computation->computation_warnings)
        <div class="alert-warning text-sm">{{ implode(' · ', $computation->computation_warnings) }}</div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Gross compensation" :value="'₱'.number_format($computation->gross_compensation, 2)" tone="gold" icon="chart" />
        <x-stat-card label="Total deductions" :value="'₱'.number_format($computation->total_deductions, 2)" tone="info" icon="document" />
        <x-stat-card label="Net pay" :value="'₱'.number_format($computation->net_pay, 2)" tone="brand" icon="chart" />
        <x-stat-card label="Status" :value="$computation->status?->label() ?? '—'" tone="info" icon="document" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Employee &amp; coverage</h2></div>
            <dl class="divide-y divide-line p-5 text-sm">
                @foreach ([
                    ['Employee', $employee->fullName().' ('.$employee->employee_number.')'],
                    ['Department', $employee->department?->name ?? '—'],
                    ['Mode', $computation->computation_mode?->label()],
                    ['Coverage', $computation->coverage_start->format('M j, Y').' – '.$computation->coverage_end->format('M j, Y')],
                    ['Payroll period', $computation->payrollPeriod?->period_name ?? '—'],
                    ['Salary basis', $computation->salary_type?->label() ?? '—'],
                    ['Intent', $computation->payable_intent?->label()],
                    ['Prepared by', ($computation->preparedByUser?->name ?? '—').($computation->prepared_at ? ' · '.$computation->prepared_at->format('M j, Y g:i A') : '')],
                ] as [$label, $value])
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="text-right font-semibold text-ink">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Breakdown</h2></div>
            <dl class="divide-y divide-line p-5 text-sm">
                @foreach ([
                    ['Basic pay', $computation->basic_pay],
                    ['Overtime', $computation->overtime_pay],
                    ['Holiday / premium', $computation->holiday_pay + $computation->premium_pay],
                    ['De minimis', $computation->de_minimis],
                    ['Other earnings', $computation->other_earnings],
                    ['Gross wage', $computation->gross_wage],
                    ['Gross compensation', $computation->gross_compensation],
                    ['Attendance deductions', -$computation->attendance_deductions],
                    ['Statutory &amp; loans', -$computation->statutory_deductions],
                    ['Other deductions', -$computation->other_deductions],
                    ['NET PAY', $computation->net_pay],
                ] as [$label, $amount])
                    <div class="flex justify-between gap-4 py-2 {{ $label === 'NET PAY' ? 'text-base font-bold text-brand-700' : '' }}">
                        <dt>{!! $label === 'NET PAY' ? 'NET PAY' : $label !!}</dt>
                        <dd class="tabular-nums {{ $amount < 0 ? 'text-critical-700' : 'font-semibold' }}">
                            {{ $amount < 0 ? '−' : '' }}₱{{ number_format(abs($amount), 2) }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Earnings</h2></div>
            <ul class="divide-y divide-line p-5 text-sm">
                @forelse ($computation->lines->where('direction', 'earning') as $line)
                    <li class="flex justify-between py-2">
                        <span>{{ $line->label }} @if($line->is_manual)<span class="text-xs text-muted">(manual)</span>@endif</span>
                        <span class="tabular-nums font-semibold">₱{{ number_format($line->amount, 2) }}</span>
                    </li>
                @empty
                    <li class="text-muted">No earnings.</li>
                @endforelse
            </ul>
        </div>
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Deductions</h2></div>
            <ul class="divide-y divide-line p-5 text-sm">
                @forelse ($computation->lines->where('direction', 'deduction') as $line)
                    <li class="flex justify-between py-2">
                        <span>{{ $line->label }}</span>
                        <span class="tabular-nums font-semibold text-critical-700">−₱{{ number_format($line->amount, 2) }}</span>
                    </li>
                @empty
                    <li class="text-muted">No deductions.</li>
                @endforelse
            </ul>
        </div>
    </div>

    @if ($computation->overrides->isNotEmpty())
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Manual overrides (audit)</h2></div>
            <div class="overflow-x-auto">
                <table class="data-table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th class="text-right">System</th>
                            <th class="text-right">Manual</th>
                            <th>Reason</th>
                            <th>By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($computation->overrides as $override)
                            <tr>
                                <td>{{ $override->field_key }}</td>
                                <td class="text-right tabular-nums">{{ number_format((float) $override->original_value, 2) }}</td>
                                <td class="text-right tabular-nums font-semibold">{{ number_format((float) $override->manual_value, 2) }}</td>
                                <td>{{ $override->reason }}</td>
                                <td class="text-xs text-muted">{{ $override->user?->name }} · {{ $override->created_at->format('M j, Y g:i A') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($computation->status?->isEditable())
        @php
            $payrollPeriods = \App\Models\PayrollPeriod::query()->orderByDesc('start_date')->limit(50)->get(['id', 'period_name', 'start_date', 'end_date', 'status']);
        @endphp
        <form method="POST" action="{{ route('admin.payroll.employees.manual-computations.update', [$employee, $computation]) }}" class="card overflow-hidden">
            @csrf
            @method('PUT')
            <div class="card-header"><h2 class="card-title">Edit draft</h2></div>
            <div class="form-card-body">
                @include('admin.payroll.manual-computations.partials.form-fields', ['computation' => $computation, 'payrollPeriods' => $payrollPeriods])
            </div>
            <div class="card-footer flex flex-wrap gap-2">
                <button type="submit" class="btn-primary">Save draft &amp; recalculate</button>
            </div>
        </form>

        <form method="POST" action="{{ route('admin.payroll.employees.manual-computations.recalculate', [$employee, $computation]) }}" class="card overflow-hidden" x-data="{ overrides: [{ field_key: 'total_basic_pay', manual_value: '', reason: '' }] }">
            @csrf
            <div class="card-header"><h2 class="card-title">Recalculate with field override</h2></div>
            <div class="form-card-body space-y-3">
                <template x-for="(row, i) in overrides" :key="i">
                    <div class="grid gap-2 md:grid-cols-4">
                        <select class="select" :name="'overrides['+i+'][field_key]'" x-model="row.field_key">
                            <option value="total_basic_pay">Total basic pay</option>
                            <option value="basic_pay">Basic pay</option>
                            <option value="overtime_pay">Overtime pay</option>
                            <option value="holiday_pay">Holiday pay</option>
                            <option value="premium_pay">Premium pay</option>
                        </select>
                        <input class="input tabular-nums" type="number" step="0.01" :name="'overrides['+i+'][manual_value]'" x-model="row.manual_value" placeholder="Amount" required>
                        <input class="input md:col-span-2" :name="'overrides['+i+'][reason]'" x-model="row.reason" placeholder="Reason (required)" required>
                    </div>
                </template>
            </div>
            <div class="card-footer">
                <button type="submit" class="btn-outline">Recalculate</button>
            </div>
        </form>
    @endif

    <div class="flex flex-wrap gap-2">
        @if ($computation->status?->isEditable())
            <form method="POST" action="{{ route('admin.payroll.employees.manual-computations.submit', [$employee, $computation]) }}">
                @csrf
                <button type="submit" class="btn-primary">Submit for review</button>
            </form>
        @endif
        @if ($computation->status === \App\Enums\ManualSalaryComputationStatus::ForReview)
            <form method="POST" action="{{ route('admin.payroll.employees.manual-computations.approve', [$employee, $computation]) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="text" name="review_notes" class="input" placeholder="Approval notes (optional)">
                <button type="submit" class="btn-primary">Approve</button>
            </form>
            <form method="POST" action="{{ route('admin.payroll.employees.manual-computations.reject', [$employee, $computation]) }}" class="flex flex-wrap items-end gap-2">
                @csrf
                <input type="text" name="review_notes" class="input" placeholder="Rejection reason" required>
                <button type="submit" class="btn-outline">Reject</button>
            </form>
        @endif
        @if ($computation->status === \App\Enums\ManualSalaryComputationStatus::Approved)
            <form method="POST" action="{{ route('admin.payroll.employees.manual-computations.finalize', [$employee, $computation]) }}" onsubmit="return confirm('Finalize this computation? This cannot be undone.');">
                @csrf
                <button type="submit" class="btn-primary">Finalize</button>
            </form>
        @endif
    </div>

    @if ($computation->remarks)
        <div class="card overflow-hidden">
            <div class="card-header"><h2 class="card-title">Remarks</h2></div>
            <p class="p-5 text-sm text-ink-soft">{{ $computation->remarks }}</p>
        </div>
    @endif
</div>
@endsection
