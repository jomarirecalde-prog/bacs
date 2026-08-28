@extends('layouts.app')

@section('title', 'DTR Correction Requests')
@section('page-title', 'DTR Correction Requests')
@section('page-subtitle', 'Track your time entry correction requests')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form class="filter-bar flex-1">
            <div class="min-w-[11rem] flex-1">
                <label class="label" for="status">Status</label>
                <select id="status" name="status" class="select">
                    <option value="">All statuses</option>
                    @foreach (\App\Enums\AttendanceCorrectionStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(($filters['status'] ?? '') === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn-secondary">Filter</button>
        </form>
        <a href="{{ route('employee.attendance-corrections.create') }}" class="btn-primary shrink-0">Request Correction</a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Field</th>
                        <th>Original</th>
                        <th>Requested</th>
                        <th>Filed</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink">{{ $row->attendance_date->format('M d, Y') }}</td>
                            <td>{{ $row->punchLabel() }}</td>
                            <td class="tabular-nums text-muted">{{ $row->formattedOriginal() }}</td>
                            <td class="tabular-nums font-semibold text-brand-700">{{ $row->formattedRequested() }}</td>
                            <td class="whitespace-nowrap text-muted">{{ $row->created_at->format('M d, Y') }}</td>
                            <td><span class="badge-{{ match($row->status->color()) { 'yellow' => 'warn', 'green' => 'brand', 'red' => 'critical', default => 'neutral' } }}">{{ $row->status->label() }}</span></td>
                            <td class="text-right whitespace-nowrap">
                                <a class="btn-outline btn-sm" href="{{ route('employee.attendance-corrections.show', $row) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No correction requests" message="Submit a request if a DTR entry needs to be corrected." icon="clock" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $requests->links() }}</div>
    </div>
</div>
@endsection
