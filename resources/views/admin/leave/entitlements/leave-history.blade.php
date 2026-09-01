@extends('layouts.app')

@section('title', 'Leave History — '.$employee->fullName())
@section('page-title', 'Leave Application History')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="space-y-6">
    @include('admin.leave.entitlements.partials.employee-header', ['employee' => $employee, 'year' => now()->year])

    <form class="filter-bar">
        <div class="sm:min-w-[11rem]">
            <label class="label" for="lh-status">Status</label>
            <select id="lh-status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach (\App\Enums\LeaveStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <div class="sm:min-w-[11rem]">
            <label class="label" for="lh-type">Leave type</label>
            <select id="lh-type" name="leave_type" class="select">
                <option value="">All types</option>
                @foreach (\App\Enums\LeaveType::cases() as $type)
                    <option value="{{ $type->value }}" @selected(($filters['leave_type'] ?? '') === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-secondary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Application No.</th>
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
                            <td class="font-semibold tabular-nums">{{ $application->application_number }}</td>
                            <td>{{ $application->leaveTypeLabel() }}</td>
                            <td class="whitespace-nowrap">{{ $application->dateRangeLabel() }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format((float) $application->requested_days, 1), '0'), '.') }}</td>
                            <td class="text-xs whitespace-nowrap">{{ $application->filedLabel() }}</td>
                            <td><span class="{{ $application->status->badgeClass() }}">{{ $application->status->label() }}</span></td>
                            <td>{{ $application->payment_type?->label() ?? '—' }}</td>
                            <td class="text-right">
                                <a class="btn-outline btn-sm" href="{{ route('admin.leave.show', $application) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state title="No leave applications" message="This employee has no leave applications yet." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $applications->links() }}</div>
    </div>

    <a href="{{ route('admin.leave.entitlements.show', $employee) }}" class="btn-outline">Back</a>
</div>
@endsection
