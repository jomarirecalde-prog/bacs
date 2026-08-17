@extends('layouts.app')

@section('title', 'Monthly DTR')
@section('page-title', 'Employee Monthly DTR')

@section('content')
<div class="flex flex-wrap gap-2 mb-4">
    <a href="{{ route('admin.dtr.index') }}" class="btn-secondary">Daily DTR</a>
    <a href="{{ route('admin.dtr.monthly') }}" class="btn-primary">Monthly DTR</a>
    @if (auth()->user()->isAdmin())
        <a href="{{ route('admin.dtr.create') }}" class="btn-secondary">Manual entry</a>
    @endif
</div>
<form class="card p-4 grid gap-3 md:grid-cols-4 mb-6">
    <select name="employee_id" class="input" required>
        <option value="">Select employee</option>
        @foreach ($employees as $emp)
            <option value="{{ $emp->id }}" @selected($employee?->id === $emp->id)>{{ $emp->fullName() }} ({{ $emp->employee_number }})</option>
        @endforeach
    </select>
    <select name="month" class="input">
        @for ($m = 1; $m <= 12; $m++)
            <option value="{{ $m }}" @selected($month == $m)>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
        @endfor
    </select>
    <select name="year" class="input">
        @for ($y = now()->year; $y >= now()->year - 5; $y--)
            <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
        @endfor
    </select>
    <button class="btn-primary">Generate</button>
</form>

@if ($employee)
    <div class="card overflow-hidden">
        <div class="px-5 py-4 border-b flex justify-between items-center">
            <div>
                <div class="font-bold">{{ $employee->fullName() }}</div>
                <div class="text-sm text-slate-500">{{ $employee->department?->name }} · {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</div>
            </div>
            <a class="btn-secondary" href="{{ route('admin.reports.monthly', ['employee_id' => $employee->id, 'month' => $month, 'year' => $year, 'export' => 'pdf']) }}">Export PDF</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Late</th><th>Undertime</th><th>Overtime</th><th>Status</th><th>Remarks</th></tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ optional($row->attendance_date)->format('M d, Y D') }}</td>
                            <td>{{ $row->time_in?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $row->time_out?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $row->totalHoursLabel() }}</td>
                            <td>{{ $row->late_minutes }}</td>
                            <td>{{ $row->undertime_minutes }}</td>
                            <td>{{ $row->overtimeHoursLabel() }}</td>
                            <td><x-status-badge :status="$row->status" /></td>
                            <td>{{ $row->remarks }} @if($row->is_edited)<span class="text-xs text-amber-700">(edited)</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="card"><x-empty-state title="Select an employee" message="Choose an employee, month, and year to generate a complete DTR." /></div>
@endif
@endsection
