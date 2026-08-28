@extends('layouts.app')

@section('title', 'Leave Approval Configuration')
@section('page-title', 'Leave Approval Configuration')
@section('page-subtitle', 'Department-based leave approval workflows')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <form class="filter-bar flex-1">
            <div class="min-w-[12rem] flex-[2]">
                <label class="label" for="cfg-q">Search departments</label>
                <input id="cfg-q" name="q" value="{{ $filters['q'] }}" class="input" placeholder="Department name">
            </div>
            <div class="min-w-[10rem]">
                <label class="label" for="cfg-status">Status</label>
                <select id="cfg-status" name="status" class="select">
                    <option value="">All statuses</option>
                    <option value="active" @selected($filters['status'] === 'active')>Active</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
                    <option value="incomplete" @selected($filters['status'] === 'incomplete')>Incomplete</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">Filter</button>
        </form>
    </div>

    <div class="alert-info">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span class="text-sm">Each department has its own dedicated approval workflow. Changes apply only to new leave applications. CEO final approver: <strong>{{ $ceoLabel }}</strong>.</span>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Immediate Supervisor/Superior</th>
                        <th>Department Head</th>
                        <th>Administrative Head</th>
                        <th>CEO Final Approval</th>
                        <th>Status</th>
                        <th>Last Updated</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($departments as $department)
                        @php
                            $workflow = $workflows[$department->id] ?? null;
                            $status = $workflow ? $workflowService->configurationStatus($workflow) : 'incomplete';
                        @endphp
                        @continue($filters['status'] !== '' && $filters['status'] !== $status)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $department->name }}</div>
                                <div class="text-xs text-muted">{{ $department->employees_count }} employee(s)</div>
                            </td>
                            <td>{{ $workflow ? $workflowService->approverSummary($workflow, \App\Enums\LeaveApprovalStage::ImmediateSupervisor) : '—' }}</td>
                            <td>{{ $workflow ? $workflowService->approverSummary($workflow, \App\Enums\LeaveApprovalStage::DepartmentHead) : '—' }}</td>
                            <td>{{ $workflow ? $workflowService->approverSummary($workflow, \App\Enums\LeaveApprovalStage::AdministrativeHead) : '—' }}</td>
                            <td>{{ $ceoLabel }}</td>
                            <td>
                                @if ($status === 'active')
                                    <span class="badge-brand">Active</span>
                                @elseif ($status === 'inactive')
                                    <span class="badge-neutral">Inactive</span>
                                @else
                                    <span class="badge-warn">Incomplete</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap text-muted">
                                {{ $workflow?->updated_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}
                                @if ($workflow?->updatedByUser)
                                    <div class="text-xs">{{ $workflow->updatedByUser->name }}</div>
                                @endif
                            </td>
                            <td class="whitespace-nowrap">
                                <div class="flex flex-wrap gap-1">
                                    <a href="{{ route('admin.leave.workflow.show', $department) }}" class="btn-outline btn-sm">Configure</a>
                                    <a href="{{ route('admin.leave.workflow.history', $department) }}" class="btn-ghost btn-sm">History</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state title="No departments found" message="Try another search term." icon="users" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($departments->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $departments->links() }}</div>
        @endif
    </div>
</div>
@endsection
