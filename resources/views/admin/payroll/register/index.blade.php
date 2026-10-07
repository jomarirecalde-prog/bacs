@extends('layouts.app')

@section('title', 'Payroll Register')
@section('page-title', 'Payroll register')
@section('page-subtitle', $period->period_name.' · '.$period->start_date->format('M j').' – '.$period->end_date->format('M j, Y'))

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <a href="{{ route('admin.payroll.periods.show', $period) }}" class="btn-outline btn-sm">← Period details</a>
    <div class="flex flex-wrap items-center gap-2">
        @if ($rows->isNotEmpty())
            @php
                $exportQuery = http_build_query(array_filter($filters ?? []));
            @endphp
            <a class="btn-outline btn-sm" href="{{ route('admin.payroll.register.export', $period).'?format=csv'.($exportQuery ? '&'.$exportQuery : '') }}">Export CSV</a>
            <a class="btn-outline btn-sm" href="{{ route('admin.payroll.register.export', $period).'?format=excel'.($exportQuery ? '&'.$exportQuery : '') }}">Export Excel</a>
            <a class="btn-outline btn-sm" href="{{ route('admin.payroll.register.export', $period).'?format=pdf'.($exportQuery ? '&'.$exportQuery : '') }}">Export PDF</a>
            <a class="btn-outline btn-sm" href="{{ route('admin.payroll.register.export', $period).'?format=reconciliation'.($exportQuery ? '&'.$exportQuery : '') }}">BACS reconciliation Excel</a>
        @endif
    </div>
    <form class="flex flex-wrap gap-2">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="input w-48" placeholder="Search employee">
        <select name="designation_id" class="select w-auto" onchange="this.form.submit()">
            <option value="">All designations</option>
            @foreach ($designations as $d)
                <option value="{{ $d->id }}" @selected(($filters['designation_id'] ?? '') == $d->id)>{{ $d->designation_name }}</option>
            @endforeach
        </select>
    </form>
</div>

<div class="card overflow-hidden">
    <div class="table-wrap overflow-x-auto">
        <table class="data-table min-w-[1200px] text-xs">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Designation</th>
                    <th class="text-right">Basic Pay</th>
                    <th class="text-right">Absent/Late/UT</th>
                    <th class="text-right">Total Basic</th>
                    <th class="text-right">OT</th>
                    <th class="text-right">Gross Wage</th>
                    <th class="text-right">De Minimis</th>
                    <th class="text-right">Gross Comp.</th>
                    <th class="text-right">Deductions</th>
                    <th class="text-right">Net Pay</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $alu = $row->absence_deduction + $row->late_deduction + $row->undertime_deduction;
                    @endphp
                    <tr>
                        <td>
                            <div class="font-semibold text-ink">{{ $row->employee_name }}</div>
                            <div class="text-muted">{{ $row->employee_number }}</div>
                        </td>
                        <td>{{ $row->designation_name ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row->basic_pay, 2) }}</td>
                        <td class="text-right tabular-nums text-critical-700">{{ number_format($alu, 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row->total_basic_pay, 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row->overtime_pay, 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row->gross_wage, 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row->de_minimis, 2) }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ number_format($row->gross_compensation, 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($row->total_deductions, 2) }}</td>
                        <td class="text-right tabular-nums font-bold text-brand-700">{{ number_format($row->net_pay, 2) }}</td>
                        <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('admin.payroll.employees.payroll.show', $row) }}">Details</a></td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="p-0"><x-empty-state title="No payroll rows" message="Run Compute full payroll on the period first." icon="document" /></td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot class="bg-canvas font-semibold">
                    <tr>
                        <td colspan="2">Totals ({{ $rows->count() }})</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['basic_pay'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['attendance_deduction'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['total_basic_pay'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['overtime_pay'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['gross_wage'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['de_minimis'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['gross_compensation'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($totals['total_deductions'], 2) }}</td>
                        <td class="text-right tabular-nums text-brand-700">{{ number_format($totals['net_pay'], 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
@endsection
