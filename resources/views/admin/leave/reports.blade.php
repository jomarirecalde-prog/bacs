@extends('layouts.app')

@section('title', 'Leave Reports')
@section('page-title', 'Leave Reports')
@section('page-subtitle', 'Generated '.$generated.' (Asia/Manila)')

@section('content')
<div class="space-y-6">
    <form class="filter-bar">
        <div class="sm:min-w-[11rem]">
            <label class="label">Department</label>
            <select name="department_id" class="select">
                <option value="">All departments</option>
                @foreach ($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[10rem]">
            <label class="label">Leave type</label>
            <select name="leave_type" class="select">
                <option value="">All types</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['leave_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[11rem]">
            <label class="label">Status</label>
            <select name="status" class="select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[9rem]">
            <label class="label">From</label>
            <input type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input">
        </div>
        <div class="sm:min-w-[9rem]">
            <label class="label">To</label>
            <input type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input">
        </div>
        <button class="btn-secondary" type="submit">Run report</button>
    </form>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-stat-card label="Applications" :value="$counts['total']" tone="blue" icon="document" />
        <x-stat-card label="Approved" :value="$counts['approved']" tone="green" icon="check" />
        <x-stat-card label="Denied" :value="$counts['denied']" tone="red" icon="x" />
        <x-stat-card label="Pending" :value="$counts['pending']" tone="yellow" icon="clock" />
        <x-stat-card label="Approved days" :value="$counts['days']" tone="gold" icon="star" />
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No.</th><th>Employee</th><th>Department</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th>Pay</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td><a class="link" href="{{ route('admin.leave.show', $application) }}">{{ $application->application_number }}</a></td>
                            <td>{{ $application->employee?->fullName() }}</td>
                            <td>{{ $application->department?->name }}</td>
                            <td>{{ $application->leaveTypeLabel() }}</td>
                            <td>{{ $application->dateRangeLabel() }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format((float) $application->requested_days, 1), '0'), '.') }}</td>
                            <td><span class="{{ $application->status->badgeClass() }}">{{ $application->status->label() }}</span></td>
                            <td>{{ $application->payment_type?->label() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state title="No matching leave records" message="Adjust the filters to generate a leave report." icon="chart" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
