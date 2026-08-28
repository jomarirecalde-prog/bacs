@extends('layouts.app')

@section('title', 'Leave Applications')
@section('page-title', 'Leave Applications')
@section('page-subtitle', 'Company-wide leave application management')

@section('content')
<div class="space-y-6">
    <form class="filter-bar">
        <div class="min-w-[12rem] flex-[2]">
            <label class="label" for="admin-leave-q">Search</label>
            <input id="admin-leave-q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Number, employee, reason">
        </div>
        <div class="min-w-[11rem]">
            <label class="label" for="admin-leave-dept">Department</label>
            <select id="admin-leave-dept" name="department_id" class="select">
                <option value="">All departments</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[11rem]">
            <label class="label" for="admin-leave-emp">Employee</label>
            <select id="admin-leave-emp" name="employee_id" class="select">
                <option value="">All employees</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected(($filters['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[10rem]">
            <label class="label" for="admin-leave-type">Leave type</label>
            <select id="admin-leave-type" name="leave_type" class="select">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['leave_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[11rem]">
            <label class="label" for="admin-leave-status">Status</label>
            <select id="admin-leave-status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[9rem]">
            <label class="label" for="admin-leave-from">From</label>
            <input id="admin-leave-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input">
        </div>
        <div class="min-w-[9rem]">
            <label class="label" for="admin-leave-to">To</label>
            <input id="admin-leave-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input">
        </div>
        <button class="btn-secondary" type="submit">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Filed</th>
                        <th>Status</th>
                        <th>Pay</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td class="font-semibold">{{ $application->application_number }}</td>
                            <td>{{ $application->employee?->fullName() }}</td>
                            <td>{{ $application->department?->name }}</td>
                            <td>{{ $application->leaveTypeLabel() }}</td>
                            <td class="whitespace-nowrap">{{ $application->dateRangeLabel() }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format((float) $application->requested_days, 1), '0'), '.') }}</td>
                            <td class="text-xs whitespace-nowrap">{{ $application->filedLabel() }}</td>
                            <td><span class="{{ $application->status->badgeClass() }}">{{ $application->status->label() }}</span></td>
                            <td>{{ $application->payment_type?->label() ?? '—' }}</td>
                            <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('admin.leave.show', $application) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-0"><x-empty-state title="No leave applications" message="Submitted leave applications will appear here." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
