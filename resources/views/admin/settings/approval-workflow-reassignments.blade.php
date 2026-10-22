@extends('layouts.app')
@section('title', 'Reassignment History')
@section('page-title', 'Approval Task Reassignment History')
@section('page-subtitle', $type->label())

@section('content')
<div class="card">
    <div class="card-body space-y-4">
        @forelse ($logs as $log)
            <div class="rounded-xl border border-line p-4 text-sm">
                <div class="font-semibold text-ink">
                    {{ class_basename($log->assignable_type) }} #{{ $log->assignable_id }}
                    · {{ str_replace('_', ' ', $log->stage) }}
                </div>
                <div class="mt-1 text-muted">
                    {{ $log->department?->name }}
                    · {{ $log->initiator?->name }}
                    · {{ $log->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}
                </div>
                <div class="mt-2">
                    {{ $log->previousUser?->name ?? 'Unassigned' }}
                    → {{ $log->newUser?->name ?? '—' }}
                    <span class="text-muted">({{ $log->previous_assignment_status }} → {{ $log->new_assignment_status }})</span>
                </div>
                @if ($log->reason)
                    <div class="mt-1 text-xs text-muted">{{ $log->reason }}</div>
                @endif
            </div>
        @empty
            <x-empty-state title="No reassignments yet" message="Pending approval tasks reassigned during configuration updates will appear here." icon="document" />
        @endforelse
    </div>
    <div class="card-footer">{{ $logs->links() }}</div>
</div>
<div class="mt-4 flex flex-wrap gap-3">
    <a href="{{ route('admin.settings.index') }}#approval-workflow" class="btn-secondary">Back to workflow settings</a>
    <a href="{{ route('admin.settings.approval-workflow.history', $type->value) }}" class="btn-outline">Configuration history</a>
</div>
@endsection
