@extends('layouts.app')
@section('title', 'My Official Time')
@section('page-title', 'My Official Time')
@section('page-subtitle', 'Track your Official Time requests')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ([
            ['Total', $counts['total']],
            ['Pending', $counts['pending']],
            ['Approved', $counts['approved']],
            ['Rejected', $counts['rejected']],
            ['Returned', $counts['returned']],
            ['Cancelled', $counts['cancelled']],
        ] as [$label, $value])
            <div class="card"><div class="card-body">
                <div class="text-xs font-bold uppercase tracking-wide text-muted">{{ $label }}</div>
                <div class="mt-1 text-2xl font-extrabold tabular-nums">{{ $value }}</div>
            </div></div>
        @endforeach
    </div>

    <div class="flex justify-end">
        <a href="{{ route('employee.official-time.create') }}" class="btn btn-primary">Request Official Time</a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request No.</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Type</th>
                        <th>Purpose</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $row)
                        <tr>
                            <td class="font-semibold" data-label="Request No.">{{ $row->request_no }}</td>
                            <td data-label="Date">{{ $row->date?->format('M j, Y') }}</td>
                            <td data-label="Time">{{ $row->timeRangeLabel() }}</td>
                            <td data-label="Type">{{ $row->officialTimeType?->name }}</td>
                            <td class="max-w-[12rem] truncate" data-label="Purpose" title="{{ $row->purpose }}">{{ $row->purpose }}</td>
                            <td data-label="Duration">{{ $row->durationLabel() }}</td>
                            <td data-label="Status"><span class="{{ $row->status->badgeClass() }}">{{ $row->status->label() }}</span></td>
                            <td data-label="Submitted">{{ $row->submittedLabel() ?? '—' }}</td>
                            <td class="text-right space-x-1" data-label="Actions">
                                <a class="btn-outline btn-sm" href="{{ route('employee.official-time.show', $row) }}">View</a>
                                @can('update', $row)
                                    <a class="btn-outline btn-sm" href="{{ route('employee.official-time.edit', $row) }}">Edit</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-0"><x-empty-state title="No Official Time requests" message="Create your first Official Time request." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif
    </div>
</div>
@endsection
