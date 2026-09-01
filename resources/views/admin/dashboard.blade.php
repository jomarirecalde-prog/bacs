@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Attendance Dashboard')
@section('page-subtitle', \App\Models\Setting::get('company_name', config('app.name')).' · '. \Carbon\Carbon::parse($date)->toFormattedDateString())

@section('content')
<div x-data="adminLive({ liveUrl: @js($liveUrl) })" class="space-y-6">
    <form method="GET" class="filter-bar">
        <div class="sm:min-w-[9rem] flex-1">
            <label class="label" for="filter-date">Date</label>
            <input id="filter-date" type="date" name="date" value="{{ $date }}" class="input">
        </div>
        <div class="sm:min-w-[10rem] flex-1">
            <label class="label" for="filter-department">Department</label>
            <select id="filter-department" name="department_id" class="select">
                <option value="">All Departments</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[10rem] flex-1">
            <label class="label" for="filter-employee">Employee</label>
            <select id="filter-employee" name="employee_id" class="select">
                <option value="">All employees</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected(($filters['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[9rem] flex-1">
            <label class="label" for="filter-status">Status</label>
            <select id="filter-status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') == $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[12rem] flex-[2]">
            <label class="label" for="filter-q">Search</label>
            <input id="filter-q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Name, ID, position">
        </div>
        <button type="submit" class="btn-primary">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
            Filter
        </button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total Employees" :value="$summary['total_employees']" live="total_employees" tone="info" icon="users" />
        <x-stat-card label="Present Today" :value="$summary['present']" live="present" tone="green" icon="check" />
        <x-stat-card label="Late Today" :value="$summary['late']" live="late" tone="yellow" icon="clock" />
        <x-stat-card label="Absent Today" :value="$summary['absent']" live="absent" tone="red" icon="x" />
        <x-stat-card label="Currently Working" :value="$summary['clocked_in']" live="clocked_in" tone="gold" icon="star" />
        <x-stat-card label="Completed" :value="$summary['completed']" live="completed" tone="green" icon="document" />
        <x-stat-card label="Missing Time Out" :value="$summary['missing_timeout']" live="missing_timeout" tone="yellow" icon="warning" />
        <x-stat-card label="On Leave" :value="$summary['on_leave']" live="on_leave" tone="blue" icon="calendar" />
    </div>

    <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
    <div class="card card-accent-info overflow-hidden xl:col-span-2">
        <div class="card-header">
            <div>
                <h2 class="card-title">Department Attendance Summary</h2>
                <p class="mt-0.5 text-xs text-muted">Calculated from live attendance records</p>
            </div>
            <span class="chip">Live · updates every 15s</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th class="text-right">Employees</th>
                        <th class="text-right">Present</th>
                        <th class="text-right">Late</th>
                        <th class="text-right">Absent</th>
                        <th class="text-right">Working</th>
                        <th class="w-40">Attendance rate</th>
                    </tr>
                </thead>
                <tbody id="live-dept-body">
                    @forelse ($departmentSummaries as $deptSummary)
                        @php
                            $headcount = max(1, (int) $deptSummary['employees']);
                            $rate = (int) round(((int) $deptSummary['present'] / $headcount) * 100);
                        @endphp
                        <tr>
                            <td class="font-semibold text-ink">{{ $deptSummary['department'] }}</td>
                            <td class="text-right tabular-nums">{{ $deptSummary['employees'] }}</td>
                            <td class="text-right font-semibold text-brand-700 tabular-nums">{{ $deptSummary['present'] }}</td>
                            <td class="text-right font-semibold text-warn-700 tabular-nums">{{ $deptSummary['late'] }}</td>
                            <td class="text-right font-semibold text-critical-600 tabular-nums">{{ $deptSummary['absent'] }}</td>
                            <td class="text-right font-semibold text-info-700 tabular-nums">{{ $deptSummary['working'] }}</td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <div class="meter">
                                        <div class="{{ $rate >= 75 ? 'meter-fill' : ($rate >= 50 ? 'meter-fill-warn' : 'meter-fill-critical') }}" style="width: {{ min(100, $rate) }}%"></div>
                                    </div>
                                    <span class="w-9 shrink-0 text-right text-xs font-bold text-muted tabular-nums">{{ $rate }}%</span>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No departments" message="Create a department to see attendance grouped here." icon="building" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

        <x-calendar.upcoming
            :events="$upcomingEvents"
            :calendar-url="route('admin.calendar.index')"
            title="Upcoming Events"
            empty-message="No upcoming holidays, meetings, announcements, or company events." />
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Employee Attendance Today</h2>
            <span class="chip">{{ $rows->total() }} employees in this view</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Position</th>
                        <th>Department</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="live-roster-body">
                    @forelse ($rows as $employee)
                        @php $record = $attendance->get($employee->id); @endphp
                        <tr class="{{ $record && ! $record->time_out && $record->time_in ? 'row-attention' : '' }}">
                            <td>
                                <a href="{{ route('admin.employees.show', $employee) }}" class="font-semibold text-ink transition hover:text-brand-700">{{ $employee->fullName() }}</a>
                                <div class="text-xs text-muted">{{ $employee->employee_number }}</div>
                            </td>
                            <td>{{ $employee->position ?? '—' }}</td>
                            <td>{{ $employee->department?->name ?? '—' }}</td>
                            <td class="font-medium text-brand-700 tabular-nums">{{ $record?->time_in?->format('g:i A') ?? '—' }}</td>
                            <td class="font-medium text-info-700 tabular-nums">{{ $record?->time_out?->format('g:i A') ?? '—' }}</td>
                            <td>
                                @if ($record)
                                    <x-status-badge :status="$record->status" />
                                @else
                                    <x-status-badge status="absent" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0"><x-empty-state title="No employees found" message="Try another department or search term." icon="users" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $rows->links() }}</div>
    </div>
</div>
@endsection
