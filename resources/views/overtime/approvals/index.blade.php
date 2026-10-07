@extends('layouts.app')
@section('title', 'Overtime Approvals')
@section('page-title', 'Overtime Approvals')

@section('content')
<div class="space-y-6">
    <nav class="pill-tabs">
        <a href="{{ route('overtime.approvals.index', ['scope' => 'endorsement']) }}" class="{{ ($scope ?? '') === 'endorsement' ? 'pill-tab-active' : 'pill-tab' }}">Endorsement</a>
        <a href="{{ route('overtime.approvals.index', ['scope' => 'final']) }}" class="{{ ($scope ?? '') === 'final' ? 'pill-tab-active' : 'pill-tab' }}">Final Approval</a>
    </nav>
    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Employee</th><th>Date</th><th>Recorded OT</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($requests as $row)
                    <tr>
                        <td>{{ $row->employee?->fullName() }}</td>
                        <td>{{ $row->attendance_date?->format('M j, Y') }}</td>
                        <td class="tabular-nums">{{ number_format($row->recorded_minutes / 60, 2) }} hrs</td>
                        <td><span class="badge-warn">{{ $row->status?->label() }}</span></td>
                        <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('overtime.approvals.show', $row) }}">Review</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-0"><x-empty-state title="No pending overtime" message="Overtime requests assigned to you will appear here." icon="clock" /></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($requests->hasPages())<div class="card-footer">{{ $requests->links() }}</div>@endif
    </div>
</div>
@endsection
