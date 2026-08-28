@extends('layouts.app')

@section('title', 'DTR Corrections')
@section('page-title', 'DTR Correction Requests')
@section('page-subtitle', 'Review employee time entry corrections')

@section('content')
<div class="space-y-6">
    <form method="GET" class="filter-bar">
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="date">Date</label>
            <input id="date" type="date" name="date" value="{{ $filters['date'] ?? '' }}" class="input">
        </div>
        <div class="min-w-[10rem] flex-1">
            <label class="label" for="employee_id">Employee</label>
            <select id="employee_id" name="employee_id" class="select">
                <option value="">All employees</option>
                @foreach ($employees as $emp)
                    <option value="{{ $emp->id }}" @selected(($filters['employee_id'] ?? '') == $emp->id)>{{ $emp->fullName() }}</option>
                @endforeach
            </select>
        </div>
        <div class="min-w-[9rem] flex-1">
            <label class="label" for="status">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Date</th>
                        <th>Field</th>
                        <th>Original</th>
                        <th>Requested</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $row)
                        <tr class="{{ $row->status->isOpen() ? 'row-attention' : '' }}">
                            <td class="font-semibold text-ink">
                                {{ $row->employee?->fullName() }}
                                <div class="text-[11px] font-normal text-muted">{{ $row->employee?->department?->name }}</div>
                            </td>
                            <td class="whitespace-nowrap">{{ $row->attendance_date->format('M d, Y') }}</td>
                            <td>{{ $row->punchLabel() }}</td>
                            <td class="tabular-nums text-muted">{{ $row->formattedOriginal() }}</td>
                            <td class="tabular-nums font-semibold text-brand-700">{{ $row->formattedRequested() }}</td>
                            <td><span class="badge-{{ match($row->status->color()) { 'yellow' => 'warn', 'green' => 'brand', 'red' => 'critical', default => 'neutral' } }}">{{ $row->status->label() }}</span></td>
                            <td class="text-right whitespace-nowrap">
                                <a class="btn-outline btn-sm" href="{{ route('admin.attendance-corrections.show', $row) }}">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No correction requests" message="Employee DTR correction requests will appear here." icon="clock" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $records->links() }}</div>
    </div>
</div>
@endsection
