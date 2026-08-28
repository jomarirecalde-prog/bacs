@extends('layouts.app')

@section('title', 'My DTR')
@section('page-title', 'My Daily Time Record')
@section('page-subtitle', 'Official cut-off attendance for '.$employee->fullName())

@section('content')
@php
    $query = $period->query();
@endphp
<div class="space-y-6">
    <div class="grid gap-3 sm:grid-cols-3">
        <div class="card card-accent-brand p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-muted">Employee</div>
            <div class="mt-1 text-sm font-bold text-ink">{{ $employee->fullName() }}</div>
            <div class="mt-0.5 text-xs text-muted">{{ $employee->employee_number }}</div>
        </div>
        <div class="card card-accent-info p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-muted">Department</div>
            <div class="mt-1 text-sm font-bold text-ink">{{ $employee->department?->name ?: '—' }}</div>
            <div class="mt-0.5 text-xs text-muted">{{ $employee->position ?: 'Staff' }}</div>
        </div>
        <div class="card card-accent-gold p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-muted">Cut-Off Period</div>
            <div class="mt-1 text-sm font-bold text-ink">{{ $period->label }}</div>
            <div class="mt-0.5 text-xs text-muted">{{ \Carbon\Carbon::parse($period->start)->format('M j, Y') }} – {{ \Carbon\Carbon::parse($period->end)->format('M j, Y') }}</div>
        </div>
    </div>

    <form class="filter-bar no-print" method="get" action="{{ route('employee.dtr') }}">
        <div class="min-w-[16rem] flex-[2]">
            <label class="label" for="dtr-period">Cut-Off</label>
            <select id="dtr-period" name="period" class="select">
                <optgroup label="Payroll cut-off">
                    @foreach ($periods as $option)
                        @if ($option->type === 'cutoff')
                            <option value="{{ $option->key }}" @selected($period->key === $option->key)>{{ $option->label }}</option>
                        @endif
                    @endforeach
                </optgroup>
                <optgroup label="Full month">
                    @foreach ($periods as $option)
                        @if ($option->type === 'month')
                            <option value="{{ $option->key }}" @selected($period->key === $option->key)>{{ $option->label }}</option>
                        @endif
                    @endforeach
                </optgroup>
            </select>
        </div>
        <button type="submit" class="btn-primary">View DTR</button>
        <span class="mx-1 hidden h-6 w-px self-center bg-line sm:block"></span>
        <a class="btn-outline btn-sm" href="{{ route('employee.attendance-corrections.create', ['date' => $period->end]) }}">Request correction</a>
        <a class="btn-primary btn-sm" href="{{ route('employee.dtr.export', $query + ['format' => 'pdf']) }}">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Download PDF
        </a>
        <a class="btn-secondary btn-sm" href="{{ route('employee.dtr.print', $query) }}" target="_blank">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
            Print DTR
        </a>
        <a class="btn-outline btn-sm" href="{{ route('employee.dtr.export', $query + ['format' => 'excel']) }}">Excel</a>
        <a class="btn-outline btn-sm" href="{{ route('employee.dtr.export', $query + ['format' => 'csv']) }}">CSV</a>
    </form>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <div>
                <div class="text-sm font-bold text-ink">Daily attendance</div>
                <div class="mt-0.5 text-xs text-muted">
                    {{ $totals['present'] }} day(s) with time entries
                    · {{ $totals['worked_label'] }} total hours
                    @if ($totals['overtime_minutes'] > 0)
                        · <span class="font-semibold text-gold-700">{{ $totals['overtime_label'] }} overtime</span>
                    @endif
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($totals['incomplete'] > 0)
                    <span class="badge-warn">{{ $totals['incomplete'] }} incomplete</span>
                @endif
                <span class="chip">{{ count($days) }} day(s)</span>
            </div>
        </div>
        <div class="table-wrap">
            <table class="data-table min-w-[56rem]">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Day</th>
                        <th>AM Time In</th>
                        <th>AM Time Out</th>
                        <th>PM Time In</th>
                        <th>PM Time Out</th>
                        <th>Overtime</th>
                        <th class="text-right">Total Hours</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($days as $day)
                        <tr class="{{ $day->incomplete ? 'row-attention' : ($day->hasOvertime() ? 'row-featured' : '') }}">
                            <td class="whitespace-nowrap font-medium text-ink">
                                <div>{{ $day->dateLabel }}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1">
                                    <x-holiday-tag :date="$day->date" :employee="$employee" compact />
                                    @if ($day->status === \App\Enums\AttendanceStatus::OnLeave)
                                        <span class="badge-info">On Leave</span>
                                    @elseif ($day->status === \App\Enums\AttendanceStatus::RestDay)
                                        <span class="badge-neutral">Rest Day</span>
                                    @elseif ($day->incomplete)
                                        <span class="badge-warn">Incomplete</span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $day->dayName }}</td>
                            <td class="tabular-nums font-medium text-brand-700">{{ $day->cell($day->amIn) }}</td>
                            <td class="tabular-nums font-medium text-brand-700">{{ $day->cell($day->amOut) }}</td>
                            <td class="tabular-nums font-medium text-info-700">{{ $day->cell($day->pmIn) }}</td>
                            <td class="tabular-nums font-medium text-info-700">{{ $day->cell($day->pmOut) }}</td>
                            <td class="tabular-nums {{ $day->hasOvertime() ? 'font-bold text-gold-700' : 'text-muted' }}">{{ $day->cell($day->overtime) }}</td>
                            <td class="text-right font-semibold tabular-nums text-ink">{{ $day->cell($day->totalHours) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state title="No records for this cut-off" message="Select another cut-off period to view your DTR." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
