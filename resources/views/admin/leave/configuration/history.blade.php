@extends('layouts.app')

@section('title', $department->name.' — Workflow History')
@section('page-title', 'Workflow Configuration History')
@section('page-subtitle', $department->name)

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.leave.workflow') }}" class="btn-outline btn-sm">All departments</a>
        <a href="{{ route('admin.leave.workflow.show', $department) }}" class="btn-ghost btn-sm">Back to configuration</a>
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap overflow-x-auto">
            <table class="data-table text-sm">
                <thead>
                    <tr>
                        <th>Date &amp; Time</th>
                        <th>Action</th>
                        <th>Summary</th>
                        <th>Updated By</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($histories as $history)
                        <tr>
                            <td class="whitespace-nowrap">{{ $history->created_at->timezone('Asia/Manila')->format('M j, Y g:i A') }}</td>
                            <td><span class="badge-neutral capitalize">{{ str_replace('_', ' ', $history->action) }}</span></td>
                            <td>{{ $history->summary ?? 'Configuration changed' }}</td>
                            <td>{{ $history->updatedByUser?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-0"><x-empty-state title="No history yet" message="Configuration changes will appear here." icon="list" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($histories->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $histories->links() }}</div>
        @endif
    </div>
</div>
@endsection
