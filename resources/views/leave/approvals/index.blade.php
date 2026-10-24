@extends('layouts.app')

@section('title', $mode === 'history' ? 'Leave Approval History' : 'Pending Leave Requests')
@section('page-title', $mode === 'history' ? 'Approval History' : 'Pending Leave Requests')
@section('page-subtitle', $mode === 'history' ? 'Leave applications you have already acted on' : 'Applications waiting for your decision')

@section('content')
<div class="space-y-6">
    <nav class="pill-tabs w-full sm:w-auto">
        <a href="{{ route('leave.approvals.index') }}" class="{{ $mode === 'pending' ? 'pill-tab-active' : 'pill-tab' }}">Pending</a>
        <a href="{{ route('leave.approvals.history') }}" class="{{ $mode === 'history' ? 'pill-tab-active' : 'pill-tab' }}">History</a>
    </nav>

    @if ($mode === 'pending')
        <form method="GET" action="{{ route('leave.approvals.index') }}" class="card p-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            @if (! empty($scope) && $scope !== 'all')
                <input type="hidden" name="scope" value="{{ $scope }}">
            @endif
            <div>
                <label class="label">Status</label>
                <select name="status" class="input">
                    <option value="">All open statuses</option>
                    @foreach ([\App\Enums\LeaveStatus::PendingSupervisor, \App\Enums\LeaveStatus::PendingCeoFinalApproval] as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected(($filters['status'] ?? '') === $statusOption->value)>{{ $statusOption->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Employee</label>
                <input type="search" name="employee" class="input" value="{{ $filters['employee'] ?? '' }}" placeholder="Name or ID">
            </div>
            <div>
                <label class="label">Filed from</label>
                <input type="date" name="filed_from" class="input" value="{{ $filters['filed_from'] ?? '' }}">
            </div>
            <div>
                <label class="label">Filed to</label>
                <input type="date" name="filed_to" class="input" value="{{ $filters['filed_to'] ?? '' }}">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="btn-primary">Filter</button>
                <a href="{{ route('leave.approvals.index', array_filter(['scope' => ($scope ?? '') !== 'all' ? $scope : null])) }}" class="btn-secondary">Reset</a>
            </div>
        </form>
    @endif

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Application No.</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Leave Type</th>
                        <th>Dates</th>
                        <th>Days</th>
                        <th>Reason</th>
                        <th>Filed</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr class="{{ $application->status === \App\Enums\LeaveStatus::PendingSupervisor ? 'row-attention' : '' }}">
                            <td class="font-semibold">{{ $application->application_number }}</td>
                            <td>{{ $application->employee?->fullName() }}</td>
                            <td>{{ $application->employee?->department?->name }}</td>
                            <td>{{ $application->employee?->position }}</td>
                            <td>{{ $application->leaveTypeLabel() }}</td>
                            <td class="whitespace-nowrap">{{ $application->dateRangeLabel() }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format((float) $application->requested_days, 1), '0'), '.') }}</td>
                            <td class="max-w-[14rem] truncate">{{ $application->reason }}</td>
                            <td class="whitespace-nowrap text-xs">{{ $application->filedLabel() }}</td>
                            <td><span class="{{ $application->status->badgeClass() }}">{{ $application->status->label() }}</span></td>
                            <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('leave.approvals.show', $application) }}">Open</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="p-0"><x-empty-state title="{{ $mode === 'history' ? 'No approval history' : 'No pending leave requests' }}" message="When a leave application is assigned to you, it will appear here." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
