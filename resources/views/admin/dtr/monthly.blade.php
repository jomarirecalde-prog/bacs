@extends('layouts.app')

@section('title', 'Monthly DTR')
@section('page-title', 'Employee Monthly DTR')
@section('page-subtitle', 'Complete monthly record for a single employee')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <nav class="pill-tabs">
            <a href="{{ route('admin.dtr.index') }}" class="pill-tab">Daily DTR</a>
            <a href="{{ route('admin.dtr.monthly') }}" class="pill-tab-active">Monthly DTR</a>
        </nav>
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.dtr.create') }}" class="btn-gold btn-sm">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Manual entry
            </a>
        @endif
    </div>

    <form class="filter-bar">
        <div class="min-w-[14rem] flex-[2]">
            <label class="label" for="monthly-employee">Employee</label>
            <select id="monthly-employee" name="employee_id" class="select" required>
                <option value="">Select employee</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected($employee?->id === $emp->id)>{{ $emp->fullName() }} ({{ $emp->employee_number }})</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="monthly-month">Month</label>
            <select id="monthly-month" name="month" class="select">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($month == $m)>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                @endfor
            </select>
        </div>
        <div class="min-w-[7rem] flex-1">
            <label class="label" for="monthly-year">Year</label>
            <select id="monthly-year" name="year" class="select">
                @for ($y = now()->year; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <button type="submit" class="btn-primary">Generate</button>
    </form>

    @if ($employee)
        <div class="card card-accent-brand overflow-hidden">
            <div class="card-header">
                <div>
                    <div class="text-sm font-bold text-ink">{{ $employee->fullName() }}</div>
                    <div class="mt-0.5 text-xs text-muted">{{ $employee->department?->name }} · {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</div>
                </div>
                <a class="btn-secondary btn-sm" href="{{ route('admin.reports.monthly', ['employee_id' => $employee->id, 'month' => $month, 'year' => $year, 'export' => 'pdf']) }}">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    Export PDF
                </a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th class="text-right">Hours</th>
                            <th class="text-right">Late</th>
                            <th class="text-right">Undertime</th>
                            <th class="text-right">Overtime</th>
                            <th>Status</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="whitespace-nowrap font-medium text-ink">
                                    {{ optional($row->attendance_date)->format('M d, Y D') }}
                                    <x-holiday-tag :date="optional($row->attendance_date)->toDateString()" :employee="$employee" compact />
                                </td>
                                <td class="font-medium text-brand-700 tabular-nums">{{ $row->time_in?->format('h:i A') ?? '—' }}</td>
                                <td class="font-medium text-info-700 tabular-nums">{{ $row->time_out?->format('h:i A') ?? '—' }}</td>
                                <td class="text-right font-semibold text-ink tabular-nums">{{ $row->totalHoursLabel() }}</td>
                                <td class="text-right tabular-nums {{ $row->late_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->late_minutes }}</td>
                                <td class="text-right tabular-nums {{ $row->undertime_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->undertime_minutes }}</td>
                                <td class="text-right tabular-nums {{ $row->overtime_minutes > 0 ? 'font-bold text-gold-700' : 'text-muted' }}">{{ $row->overtimeHoursLabel() }}</td>
                                <td><x-status-badge :status="$row->status" /></td>
                                <td>
                                    {{ $row->remarks }}
                                    @if ($row->is_edited)<span class="badge-warn">edited</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="p-0"><x-empty-state title="No records for this month" message="This employee has no attendance stored for the selected period." icon="calendar" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="card"><x-empty-state title="Select an employee" message="Choose an employee, month, and year to generate a complete DTR." icon="users" /></div>
    @endif
</div>
@endsection
