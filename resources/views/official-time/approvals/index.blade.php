@extends('layouts.app')
@section('title', 'Official Time Approvals')
@section('page-title', 'Official Time Approvals')

@section('content')
<div class="space-y-4">
    <nav class="pill-tabs">
        <a href="{{ route('official-time.approvals.index') }}" class="{{ ($mode ?? 'pending') === 'pending' && ($scope ?? 'all') === 'all' ? 'pill-tab-active' : 'pill-tab' }}">Pending</a>
        <a href="{{ route('official-time.approvals.index', ['scope' => 'endorsement']) }}" class="{{ ($scope ?? '') === 'endorsement' ? 'pill-tab-active' : 'pill-tab' }}">Endorsement</a>
        <a href="{{ route('official-time.approvals.index', ['scope' => 'final']) }}" class="{{ ($scope ?? '') === 'final' ? 'pill-tab-active' : 'pill-tab' }}">Final Approval</a>
        <a href="{{ route('official-time.approvals.history') }}" class="{{ ($mode ?? '') === 'history' ? 'pill-tab-active' : 'pill-tab' }}">History</a>
    </nav>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request No.</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Duration</th>
                        <th>Purpose</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $row)
                        <tr>
                            <td class="font-semibold">{{ $row->request_no }}</td>
                            <td>{{ $row->employee?->fullName() }}</td>
                            <td>{{ $row->department?->name ?? '—' }}</td>
                            <td>{{ $row->date?->format('M j, Y') }}</td>
                            <td>{{ $row->timeRangeLabel() }}</td>
                            <td>{{ $row->durationLabel() }}</td>
                            <td class="max-w-[10rem] truncate" title="{{ $row->purpose }}">{{ $row->purpose }}</td>
                            <td><span class="{{ $row->status->badgeClass() }}">{{ $row->status->label() }}</span></td>
                            <td>{{ $row->submittedLabel() ?? '—' }}</td>
                            <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('official-time.approvals.show', $row) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="p-0"><x-empty-state title="No records" message="No Official Time requests in this list." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif
    </div>
</div>
@endsection
