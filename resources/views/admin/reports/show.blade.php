@extends('layouts.app')

@section('title', $title)
@section('page-title', $title)

@section('content')
<form method="GET" class="card p-4 grid gap-3 md:grid-cols-6 mb-6 print:hidden">
    @if ($type === 'monthly')
        <select name="employee_id" class="input md:col-span-2" required>
            <option value="">Select employee</option>
            @foreach ($filters['employees'] as $emp)
                <option value="{{ $emp->id }}" @selected(($employee->id ?? request('employee_id')) == $emp->id)>{{ $emp->fullName() }}</option>
            @endforeach
        </select>
        <select name="month" class="input">
            @for ($m = 1; $m <= 12; $m++)
                <option value="{{ $m }}" @selected(($month ?? now()->month) == $m)>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
            @endfor
        </select>
        <select name="year" class="input">
            @for ($y = now()->year; $y >= now()->year - 5; $y--)
                <option value="{{ $y }}" @selected(($year ?? now()->year) == $y)>{{ $y }}</option>
            @endfor
        </select>
    @else
        <input type="date" name="date" value="{{ $filters['values']['date'] ?? '' }}" class="input" placeholder="Date">
        <input type="date" name="date_from" value="{{ $filters['values']['date_from'] ?? '' }}" class="input">
        <input type="date" name="date_to" value="{{ $filters['values']['date_to'] ?? '' }}" class="input">
        <select name="department_id" class="input">
            <option value="">All departments</option>
            @foreach ($filters['departments'] as $dept)
                <option value="{{ $dept->id }}" @selected(($filters['values']['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
        <select name="employee_id" class="input">
            <option value="">All employees</option>
            @foreach ($filters['employees'] as $emp)
                <option value="{{ $emp->id }}" @selected(($filters['values']['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
            @endforeach
        </select>
        <select name="status" class="input">
            <option value="">All statuses</option>
            @foreach ($filters['statuses'] as $status)
                <option value="{{ $status->value }}" @selected(($filters['values']['status'] ?? '') == $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    @endif
    <div class="md:col-span-6 flex flex-wrap gap-2">
        <button class="btn-primary" name="export" value="">View</button>
        <button class="btn-secondary" name="export" value="pdf">PDF</button>
        <button class="btn-secondary" name="export" value="excel">Excel</button>
        <button class="btn-secondary" name="export" value="csv">CSV</button>
        <button type="button" class="btn-secondary" onclick="window.print()">Print</button>
    </div>
</form>

<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Employee</th><th>Department</th><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Late</th><th>UT</th><th>OT</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @php $list = method_exists($rows, 'items') ? $rows : $rows; @endphp
                @forelse ($list as $row)
                    <tr>
                        <td>{{ $row->employee?->fullName() ?? ($employee->fullName() ?? '—') }}</td>
                        <td>{{ $row->employee?->department?->name ?? ($employee->department?->name ?? '—') }}</td>
                        <td>{{ optional($row->attendance_date)->format('M d, Y') }}</td>
                        <td>{{ $row->time_in?->format('h:i A') ?? '—' }}</td>
                        <td>{{ $row->time_out?->format('h:i A') ?? '—' }}</td>
                        <td>{{ $row->totalHoursLabel() }}</td>
                        <td>{{ $row->late_minutes }}</td>
                        <td>{{ $row->undertime_minutes }}</td>
                        <td>{{ $row->overtime_minutes }}</td>
                        <td><x-status-badge :status="$row->status" /></td>
                    </tr>
                @empty
                    <tr><td colspan="10"><x-empty-state title="No report data" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if (method_exists($rows, 'links'))
        <div class="px-5 py-3 print:hidden">{{ $rows->links() }}</div>
    @endif
</div>
@endsection
