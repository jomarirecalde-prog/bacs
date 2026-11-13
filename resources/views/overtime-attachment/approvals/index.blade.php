@extends('layouts.app')
@section('title', 'OT Attachment Approvals')
@section('page-title', 'OT Attachment Approvals')
@section('page-subtitle', ($mode ?? 'pending') === 'history' ? 'Your decision history' : 'Pending endorsement and approval')

@section('content')
<div class="space-y-4">
  @if (($mode ?? 'pending') === 'pending')
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('overtime-attachment.approvals.index') }}" class="{{ ($scope ?? 'all') === 'all' ? 'pill-tab-active' : 'pill-tab' }}">Pending</a>
        <a href="{{ route('overtime-attachment.approvals.index', ['scope' => 'endorsement']) }}" class="{{ ($scope ?? '') === 'endorsement' ? 'pill-tab-active' : 'pill-tab' }}">Endorsement</a>
        <a href="{{ route('overtime-attachment.approvals.index', ['scope' => 'final']) }}" class="{{ ($scope ?? '') === 'final' ? 'pill-tab-active' : 'pill-tab' }}">Final Approval</a>
        <a href="{{ route('overtime-attachment.approvals.history') }}" class="pill-tab">History</a>
    </div>
  @else
    <a href="{{ route('overtime-attachment.approvals.index') }}" class="btn-outline btn-sm">Back to pending</a>
  @endif

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Report</th>
                        <th>Employee</th>
                        <th>Coverage</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $row)
                        <tr>
                            <td class="font-semibold">{{ $row->report_no }}</td>
                            <td>{{ $row->employee?->fullName() }}</td>
                            <td>{{ $row->coverageLabel() }}</td>
                            <td>{{ $row->durationLabel() }}</td>
                            <td><span class="{{ $row->status->badgeClass() }}">{{ $row->status->label() }}</span></td>
                            <td>{{ $row->submittedLabel() ?? '—' }}</td>
                            <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('overtime-attachment.approvals.show', $row) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No reports" message="Nothing in this queue." icon="document" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($reports->hasPages())<div class="card-footer">{{ $reports->links() }}</div>@endif
    </div>
</div>
@endsection
