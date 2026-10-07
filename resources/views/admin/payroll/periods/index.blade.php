@extends('layouts.app')

@section('title', 'Payroll Periods')
@section('page-title', 'Payroll Periods')
@section('page-subtitle', 'Cut-off aligned payroll cycles')

@section('content')
<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    <form class="flex flex-wrap gap-2">
        <select name="status" class="select w-auto" onchange="this.form.submit()">
            <option value="">All statuses</option>
            @foreach ($statuses as $status)
                <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
            @endforeach
        </select>
    </form>
    <a href="{{ route('admin.payroll.periods.create') }}" class="btn-primary btn-sm">New period</a>
</div>

<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Dates</th>
                    <th>Pay date</th>
                    <th>Summaries</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($periods as $period)
                    <tr>
                        <td class="font-semibold text-ink">{{ $period->period_name }}</td>
                        <td class="text-sm text-muted">{{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }}</td>
                        <td>{{ $period->payroll_date?->format('M j, Y') ?? '—' }}</td>
                        <td class="tabular-nums">{{ $period->attendance_summaries_count }}</td>
                        <td><span class="badge-neutral">{{ $period->status?->label() }}</span></td>
                        <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('admin.payroll.periods.show', $period) }}">Open</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-0"><x-empty-state title="No payroll periods" message="Create a period using your DTR cut-off dates." icon="calendar" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($periods->hasPages())
        <div class="border-t border-line p-4">{{ $periods->links() }}</div>
    @endif
</div>
@endsection
