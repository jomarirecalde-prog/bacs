@extends('layouts.app')
@section('title', 'Official Business Endorsements')
@section('page-title', $mode === 'history' ? 'Official Business Endorsement History' : 'Pending Official Business Endorsements')
@section('page-subtitle', $mode === 'history' ? 'Requests you have already acted on' : 'Official business waiting for your endorsement')

@section('content')
<div class="space-y-6">
    <nav class="pill-tabs w-full sm:w-auto">
        <a href="{{ route('official-business.approvals.index') }}" class="{{ $mode === 'pending' ? 'pill-tab-active' : 'pill-tab' }}">Pending</a>
        <a href="{{ route('official-business.approvals.history') }}" class="{{ $mode === 'history' ? 'pill-tab-active' : 'pill-tab' }}">History</a>
    </nav>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Requester</th>
                        <th>Department</th>
                        <th>Destination</th>
                        <th>Dates</th>
                        <th>Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($requests as $ob)
                        <tr class="{{ $ob->status === \App\Enums\OfficialBusinessStatus::PendingSupervisor ? 'row-attention' : '' }}">
                            <td class="font-semibold text-ink" data-label="Reference">{{ $ob->reference_number }}</td>
                            <td data-label="Requester">{{ $ob->employee?->fullName() ?? '—' }}</td>
                            <td data-label="Department">{{ $ob->employee?->department?->name ?? '—' }}</td>
                            <td class="max-w-[14rem] truncate" data-label="Destination" title="{{ $ob->destinationLabel() }}">{{ $ob->destinationLabel() }}</td>
                            <td class="whitespace-nowrap" data-label="Dates">{{ $ob->dateRangeLabel() }}</td>
                            <td data-label="Status"><span class="{{ $ob->status->badgeClass() }}">{{ $ob->status->label() }}</span></td>
                            <td class="text-right" data-label="Action">
                                <a class="btn-outline btn-sm" href="{{ route('official-business.approvals.show', $ob) }}">Review</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-0">
                                <x-empty-state
                                    title="{{ $mode === 'history' ? 'No endorsement history' : 'No pending official business' }}"
                                    message="When a request is assigned to you for endorsement, it will appear here."
                                    icon="document"
                                />
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
