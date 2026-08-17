@extends('layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Attendance Dashboard')
@section('page-subtitle', \App\Models\Setting::get('company_name', config('app.name')).' · '. \Carbon\Carbon::parse($date)->toFormattedDateString())

@section('content')
<div x-data="adminLive()" class="space-y-6">
    <form method="GET" class="card p-4 grid gap-3 md:grid-cols-5">
        <input type="date" name="date" value="{{ $date }}" class="input">
        <select name="department_id" class="input">
            <option value="">All Departments</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
        <select name="employee_id" class="input">
            <option value="">All employees</option>
            @foreach ($employees as $emp)
                <option value="{{ $emp->id }}" @selected(($filters['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
            @endforeach
        </select>
        <select name="status" class="input">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') == $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <input name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Search name, ID, position">
            <button class="btn-primary">Filter</button>
        </div>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card label="Total Employees" :value="$summary['total_employees']" />
        <x-stat-card label="Present Today" :value="$summary['present']" tone="green" />
        <x-stat-card label="Late Today" :value="$summary['late']" tone="orange" />
        <x-stat-card label="Absent Today" :value="$summary['absent']" tone="red" />
        <x-stat-card label="Currently Working" :value="$summary['clocked_in']" tone="teal" />
        <x-stat-card label="Completed" :value="$summary['completed']" tone="green" />
        <x-stat-card label="Missing Time Out" :value="$summary['missing_timeout']" tone="yellow" />
        <x-stat-card label="On Leave" :value="$summary['on_leave']" tone="blue" />
    </div>

    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b">
            <h2 class="font-bold">Department Attendance Summary</h2>
            <p class="text-xs text-slate-500">Calculated from live attendance records</p>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Present</th>
                        <th>Late</th>
                        <th>Absent</th>
                        <th>Working</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departmentSummaries as $deptSummary)
                        <tr>
                            <td class="font-medium">{{ $deptSummary['department'] }}</td>
                            <td>{{ $deptSummary['employees'] }}</td>
                            <td>{{ $deptSummary['present'] }}</td>
                            <td>{{ $deptSummary['late'] }}</td>
                            <td>{{ $deptSummary['absent'] }}</td>
                            <td>{{ $deptSummary['working'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state title="No departments" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="flex items-center justify-between px-5 py-4 border-b">
            <h2 class="font-bold">Employee Attendance Today</h2>
            <span class="text-xs text-slate-500">{{ $rows->total() }} employees in this view</span>
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
                <tbody>
                    @forelse ($rows as $employee)
                        @php $record = $attendance->get($employee->id); @endphp
                        <tr>
                            <td class="font-medium">
                                <a href="{{ route('admin.employees.show', $employee) }}" class="hover:text-brand-700">{{ $employee->fullName() }}</a>
                                <div class="text-xs text-slate-500">{{ $employee->employee_number }}</div>
                            </td>
                            <td>{{ $employee->position ?? '—' }}</td>
                            <td>{{ $employee->department?->name ?? '—' }}</td>
                            <td>{{ $record?->time_in?->format('g:i A') ?? '—' }}</td>
                            <td>{{ $record?->time_out?->format('g:i A') ?? '—' }}</td>
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
                            <td colspan="6"><x-empty-state title="No employees found" message="Try another department or search term." /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $rows->links() }}</div>
    </div>
</div>

<script>
function adminLive() {
    return {
        serverTime: @json(now()->format('h:i:s A')),
        init() {
            setInterval(() => this.refresh(), 15000);
        },
        async refresh() {
            const params = new URLSearchParams(window.location.search);
            const res = await fetch('{{ route('admin.dashboard.live') }}?' + params.toString(), { headers: { Accept: 'application/json' } });
            const data = await res.json();
            this.serverTime = data.server_time;
        }
    }
}
</script>
@endsection
