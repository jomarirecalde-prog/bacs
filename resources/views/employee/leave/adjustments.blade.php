@extends('layouts.app')

@section('title', 'My Leave Adjustment History')
@section('page-title', 'Leave Adjustment History')
@section('page-subtitle', 'Read-only audit trail of balance changes')

@section('content')
<div class="space-y-6">
    <form class="filter-bar">
        <input type="hidden" name="year" value="{{ $year }}">
        <div class="sm:min-w-[12rem]">
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
                        <th>Adjustment</th>
                        <th>New balance</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($adjustments as $adjustment)
                        <tr>
                            <td class="whitespace-nowrap">{{ $adjustment->recorded_at->format('M j, Y g:i A') }}</td>
                            <td>{{ $adjustment->leaveTypeLabel() }}</td>
                            <td>{{ $adjustment->action_type->label() }}</td>
                            <td class="tabular-nums">{{ $adjustment->adjustmentLabel() }}</td>
                            <td class="tabular-nums font-semibold">{{ rtrim(rtrim(number_format($adjustment->new_balance, 1), '0'), '.') }}</td>
                            <td>{{ $adjustment->reason }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-0"><x-empty-state title="No adjustments recorded" message="Balance changes will appear here when HR processes leave or applies manual adjustments." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $adjustments->links() }}</div>
    </div>

    <a href="{{ route('employee.leave.balances', ['year' => $year]) }}" class="btn-outline">Back to balances</a>
</div>
@endsection
