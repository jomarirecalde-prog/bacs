@extends('layouts.app')

@section('title', $title)
@section('page-title', $title)
@section('page-subtitle', 'Official attendance report · Asia/Manila')

@section('content')
<div class="space-y-6">
    <div class="no-print">
        <a href="{{ route('admin.reports.index') }}" class="btn-outline btn-sm">
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            All reports
        </a>
    </div>

    <form method="GET" class="filter-bar no-print">
        @if ($type === 'monthly')
            <div class="sm:min-w-[14rem] flex-[2]">
                <label class="label" for="rep-employee">Employee</label>
                <select id="rep-employee" name="employee_id" class="select" required>
                    <option value="">Select employee</option>
                    @foreach ($filters['employees'] as $emp)
                        <option value="{{ $emp->id }}" @selected(($employee->id ?? request('employee_id')) == $emp->id)>{{ $emp->fullName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:min-w-[9rem] flex-1">
                <label class="label" for="rep-month">Month</label>
                <select id="rep-month" name="month" class="select">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected(($month ?? now()->month) == $m)>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                    @endfor
                </select>
            </div>
            <div class="sm:min-w-[7rem] flex-1">
                <label class="label" for="rep-year">Year</label>
                <select id="rep-year" name="year" class="select">
                    @for ($y = now()->year; $y >= now()->year - 5; $y--)
                        <option value="{{ $y }}" @selected(($year ?? now()->year) == $y)>{{ $y }}</option>
                    @endfor
                </select>
            </div>
        @else
            <div class="sm:min-w-[9rem] flex-1">
                <label class="label" for="rep-date">Date</label>
                <input id="rep-date" type="date" name="date" value="{{ $filters['values']['date'] ?? '' }}" class="input">
            </div>
            <div class="sm:min-w-[9rem] flex-1">
                <label class="label" for="rep-date-from">From</label>
                <input id="rep-date-from" type="date" name="date_from" value="{{ $filters['values']['date_from'] ?? '' }}" class="input">
            </div>
            <div class="sm:min-w-[9rem] flex-1">
                <label class="label" for="rep-date-to">To</label>
                <input id="rep-date-to" type="date" name="date_to" value="{{ $filters['values']['date_to'] ?? '' }}" class="input">
            </div>
            <div class="sm:min-w-[10rem] flex-1">
                <label class="label" for="rep-dept">Department</label>
                <select id="rep-dept" name="department_id" class="select">
                    <option value="">All departments</option>
                    @foreach ($filters['departments'] as $dept)
                        <option value="{{ $dept->id }}" @selected(($filters['values']['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:min-w-[10rem] flex-1">
                <label class="label" for="rep-emp">Employee</label>
                <select id="rep-emp" name="employee_id" class="select">
                    <option value="">All employees</option>
                    @foreach ($filters['employees'] as $emp)
                        <option value="{{ $emp->id }}" @selected(($filters['values']['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:min-w-[9rem] flex-1">
                <label class="label" for="rep-status">Status</label>
                <select id="rep-status" name="status" class="select">
                    <option value="">All statuses</option>
                    @foreach ($filters['statuses'] as $status)
                        <option value="{{ $status->value }}" @selected(($filters['values']['status'] ?? '') == $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="flex w-full flex-col gap-2 border-t border-line pt-4 sm:flex-row sm:flex-wrap sm:items-center">
            <button type="submit" class="btn-primary w-full sm:w-auto" name="export" value="">View report</button>
            <div class="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center">
                <button type="submit" class="btn-secondary btn-sm w-full sm:w-auto" name="export" value="pdf">PDF</button>
                <button type="submit" class="btn-secondary btn-sm w-full sm:w-auto" name="export" value="excel">Excel</button>
                <button type="submit" class="btn-secondary btn-sm w-full sm:w-auto" name="export" value="csv">CSV</button>
                <button type="button" class="btn-outline btn-sm w-full sm:w-auto" onclick="window.print()">Print</button>
            </div>
        </div>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Late</th>
                        <th class="text-right">UT</th>
                        <th class="text-right">OT</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="font-semibold text-ink">{{ $row->employee?->fullName() ?? ($employee->fullName() ?? '—') }}</td>
                            <td>{{ $row->employee?->department?->name ?? ($employee->department?->name ?? '—') }}</td>
                            <td class="whitespace-nowrap">
                                {{ optional($row->attendance_date)->format('M d, Y') }}
                                {{-- Flags rows that land on a declared holiday so absences are never mistaken for one. --}}
                                <x-holiday-tag :date="optional($row->attendance_date)->toDateString()" :employee="$row->employee ?? $employee ?? null" compact />
                            </td>
                            <td class="font-medium text-brand-700 tabular-nums">{{ $row->time_in?->format('h:i A') ?? '—' }}</td>
                            <td class="font-medium text-info-700 tabular-nums">{{ $row->time_out?->format('h:i A') ?? '—' }}</td>
                            <td class="text-right font-semibold text-ink tabular-nums">{{ $row->totalHoursLabel() }}</td>
                            <td class="text-right tabular-nums {{ $row->late_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->late_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->undertime_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->undertime_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->overtime_minutes > 0 ? 'font-bold text-gold-700' : 'text-muted' }}">{{ $row->overtime_minutes }}</td>
                            <td><x-status-badge :status="$row->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-0"><x-empty-state title="No report data" message="Adjust the filters above and generate the report again." icon="chart" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if (method_exists($rows, 'links'))
            <div class="card-footer no-print">{{ $rows->links() }}</div>
        @endif
    </div>
</div>
@endsection
