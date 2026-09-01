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
