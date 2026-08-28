@extends('layouts.app')

@section('title', 'My Leave Applications')
@section('page-title', 'My Leave Applications')
@section('page-subtitle', 'Track status, approval progress, and official forms')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form class="filter-bar flex-1">
            <div class="min-w-[12rem] flex-[2]">
                <label class="label" for="leave-q">Search</label>
                <input id="leave-q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Application number or reason">
            </div>
            <div class="min-w-[11rem] flex-1">
                <label class="label" for="leave-status">Status</label>
                <select id="leave-status" name="status" class="select">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\LeaveStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[9rem]">
                <label class="label" for="leave-from">From</label>
                <input id="leave-from" type="date" name="from" value="{{ $filters['from'] ?? '' }}" class="input">
            </div>
            <div class="min-w-[9rem]">
                <label class="label" for="leave-to">To</label>
                <input id="leave-to" type="date" name="to" value="{{ $filters['to'] ?? '' }}" class="input">
            </div>
            <button type="submit" class="btn-secondary">Search</button>
        </form>
        <a href="{{ route('employee.leave.apply') }}" class="btn-primary shrink-0">Apply for Leave</a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Application No.</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Date Filed</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td class="font-semibold tabular-nums">{{ $application->application_number }}</td>
                            <td>{{ $application->leaveTypeLabel() }}</td>
                            <td class="whitespace-nowrap">{{ $application->dateRangeLabel() }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format((float) $application->requested_days, 1), '0'), '.') }}</td>
                            <td class="whitespace-nowrap text-xs">{{ $application->filedLabel() }}</td>
                            <td><span class="{{ $application->status->badgeClass() }}">{{ $application->status->label() }}</span></td>
                            <td class="text-right">
                                <a class="btn-outline btn-sm" href="{{ route('employee.leave.show', $application) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No leave applications" message="Submit your first official leave form when you need time off." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
