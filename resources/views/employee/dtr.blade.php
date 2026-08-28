@extends('layouts.app')

@section('title', 'My DTR')
@section('page-title', 'My Monthly DTR')
@section('page-subtitle', 'Official monthly Daily Time Record')

@section('content')
<div class="space-y-6">
    <form class="filter-bar no-print">
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="dtr-month">Month</label>
            <select id="dtr-month" name="month" class="select">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($month == $m)>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                @endfor
            </select>
        </div>
        <div class="min-w-[7rem] flex-1">
            <label class="label" for="dtr-year">Year</label>
            <select id="dtr-year" name="year" class="select">
                @for ($y = now()->year; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <button type="submit" class="btn-primary">View</button>
        <span class="mx-1 hidden h-6 w-px self-center bg-line sm:block"></span>
        <a class="btn-secondary btn-sm" href="{{ route('employee.dtr.export', ['month' => $month, 'year' => $year, 'format' => 'pdf']) }}">PDF</a>
        <a class="btn-secondary btn-sm" href="{{ route('employee.dtr.export', ['month' => $month, 'year' => $year, 'format' => 'excel']) }}">Excel</a>
        <a class="btn-secondary btn-sm" href="{{ route('employee.dtr.export', ['month' => $month, 'year' => $year, 'format' => 'csv']) }}">CSV</a>
        <a class="btn-outline btn-sm" href="{{ route('employee.dtr.print', ['month' => $month, 'year' => $year]) }}" target="_blank">Print</a>
    </form>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <div>
                <div class="text-sm font-bold text-ink">{{ $employee->fullName() }}</div>
                <div class="mt-0.5 text-xs text-muted">{{ $employee->department?->name }} · {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</div>
            </div>
            <span class="chip">{{ count($rows) }} day(s) recorded</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th class="text-right">Total Hours</th>
                        <th class="text-right">Late</th>
                        <th class="text-right">Undertime</th>
                        <th class="text-right">Overtime</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink">
                                {{ optional($row->attendance_date)->format('M d, Y D') }}
                                <x-holiday-tag :date="optional($row->attendance_date)->toDateString()" :employee="$employee" compact />
                            </td>
                            <td>
                                <span class="font-medium text-brand-700 tabular-nums">{{ $row->time_in?->format('h:i A') ?? '—' }}</span>
                                @if ($row->time_in_station_name)
                                    <div class="text-[11px] text-muted">{{ $row->time_in_station_name }}</div>
                                @endif
                            </td>
                            <td>
                                <span class="font-medium text-info-700 tabular-nums">{{ $row->time_out?->format('h:i A') ?? '—' }}</span>
                                @if ($row->time_out_station_name)
                                    <div class="text-[11px] text-muted">{{ $row->time_out_station_name }}</div>
                                @endif
                            </td>
                            <td class="text-right font-semibold text-ink tabular-nums">{{ $row->totalHoursLabel() }}</td>
                            <td class="text-right tabular-nums {{ $row->late_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->late_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->undertime_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->undertime_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->overtime_minutes > 0 ? 'font-bold text-gold-700' : 'text-muted' }}">{{ $row->overtimeHoursLabel() }}</td>
                            <td>
                                <x-status-badge :status="$row->status" />
                                @if ($row->is_edited)<div class="mt-1"><span class="badge-warn">Edited by admin</span></div>@endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state title="No records for this month" message="Select another month or year to view your DTR." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
