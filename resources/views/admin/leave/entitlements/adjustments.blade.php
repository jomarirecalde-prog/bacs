@extends('layouts.app')

@section('title', 'Adjustment History — '.$employee->fullName())
@section('page-title', 'Leave Balance Adjustment History')
@section('page-subtitle', $employee->fullName())

@section('content')
<div class="space-y-6">
    @include('admin.leave.entitlements.partials.employee-header', ['employee' => $employee, 'year' => $year])

    <form class="filter-bar">
        <input type="hidden" name="year" value="{{ $year }}">
        <div class="min-w-[12rem]">
            <label class="label" for="adj-type">Leave type</label>
            <select id="adj-type" name="leave_type_code" class="select">
                <option value="">All types</option>
                @foreach ($types as $type)
                    @if ($type->code === 'special')
                        @continue
                    @endif
                    <option value="{{ $type->code }}" @selected(($filters['leave_type_code'] ?? '') === $type->code)>{{ $type->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-secondary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Date & time</th>
                        <th>Leave type</th>
                        <th>Action</th>
                        <th>Prev. entitlement</th>
                        <th>New entitlement</th>
                        <th>Prev. balance</th>
                        <th>Adjustment</th>
                        <th>New balance</th>
                        <th>Reason</th>
                        <th>Related application</th>
                        <th>Updated by</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td class="whitespace-nowrap">{{ $adjustment->recorded_at->format('M j, Y g:i A') }}</td>
                            <td>{{ $adjustment->leaveTypeLabel() }}</td>
                            <td>{{ $adjustment->action_type->label() }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format($adjustment->previous_entitlement, 1), '0'), '.') }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format($adjustment->new_entitlement, 1), '0'), '.') }}</td>
                            <td class="tabular-nums">{{ rtrim(rtrim(number_format($adjustment->previous_balance, 1), '0'), '.') }}</td>
                            <td class="tabular-nums">{{ $adjustment->adjustmentLabel() }}</td>
                            <td class="tabular-nums font-semibold">{{ rtrim(rtrim(number_format($adjustment->new_balance, 1), '0'), '.') }}</td>
                            <td class="max-w-xs">{{ $adjustment->reason }}</td>
                            <td>
                                @if ($adjustment->leaveApplication)
                                    <a class="link" href="{{ route('admin.leave.show', $adjustment->leaveApplication) }}">{{ $adjustment->leaveApplication->application_number }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="whitespace-nowrap">{{ $adjustment->authorized_by_name ?? $adjustment->updatedBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="p-0"><x-empty-state title="No adjustments recorded" message="Manual adjustments and approved leave deductions will appear here." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $adjustments->links() }}</div>
    </div>

    <a href="{{ route('admin.leave.entitlements.show', ['employee' => $employee, 'year' => $year]) }}" class="btn-outline">Back</a>
</div>
@endsection
