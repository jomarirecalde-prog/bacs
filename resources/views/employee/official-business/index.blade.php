@extends('layouts.app')
@section('title', 'Official Business')
@section('page-title', 'Official Business')
@section('page-subtitle', 'Create and monitor official business requests')

@section('content')
<div class="space-y-6">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        @foreach ([
            ['Total', $counts['total']],
            ['Pending', $counts['pending']],
            ['Approved', $counts['approved']],
            ['Rejected', $counts['rejected']],
            ['Cancelled', $counts['cancelled']],
            ['Completed', $counts['completed']],
        ] as [$label, $value])
            <div class="card">
                <div class="card-body">
                    <div class="text-xs font-bold uppercase tracking-wide text-muted">{{ $label }}</div>
                    <div class="mt-1 text-2xl font-extrabold tabular-nums text-ink">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="pill-tabs w-full overflow-x-auto sm:w-auto">
            @foreach ([
                'mine' => 'My Requests',
                'endorsement' => 'For My Endorsement',
                'approval' => 'For My Approval',
                'participation' => 'OB Participation',
            ] as $key => $label)
                <a href="{{ route('employee.official-business.index', ['tab' => $key]) }}"
                   class="{{ $tab === $key ? 'pill-tab-active' : 'pill-tab' }}">{{ $label }}</a>
            @endforeach
        </nav>
        <a href="{{ route('employee.official-business.create') }}" class="btn btn-primary shrink-0">New OB Request</a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Participants</th>
                        <th>Destination</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $ob)
                        <tr>
                            <td class="font-semibold text-ink" data-label="Reference">{{ $ob->reference_number }}</td>
                            <td class="max-w-[14rem]" data-label="Participants">
                                {{ $ob->participants->pluck('participant_name')->take(2)->implode(', ') ?: '—' }}
                            </td>
                            <td class="max-w-[14rem] truncate" data-label="Destination" title="{{ $ob->destinationLabel() }}">{{ $ob->destinationLabel() }}</td>
                            <td class="whitespace-nowrap" data-label="Dates">{{ $ob->dateRangeLabel() }}</td>
                            <td data-label="Status"><span class="{{ $ob->status->badgeClass() }}">{{ $ob->status->label() }}</span></td>
                            <td class="text-right" data-label="Action">
                                <a class="btn-outline btn-sm" href="{{ route('employee.official-business.show', $ob) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="p-0">
                                <x-empty-state title="No official business requests" message="Create a new request or switch tabs to see other lists." icon="document" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
</div>
@endsection
