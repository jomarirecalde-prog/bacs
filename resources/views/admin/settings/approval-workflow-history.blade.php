@extends('layouts.app')
@section('title', 'Workflow History')
@section('page-title', 'Approval Workflow History')
@section('page-subtitle', $type->label())

@section('content')
<div class="card">
    <div class="card-body space-y-4">
        @forelse ($histories as $history)
            <div class="rounded-xl border border-line p-4 text-sm">
                <div class="font-semibold">Version {{ $history->version }} · {{ $history->updater?->name }} · {{ $history->created_at?->timezone('Asia/Manila')->format('M j, Y g:i A') }}</div>
                <div class="text-muted">{{ $history->summary }}</div>
            </div>
        @empty
            <x-empty-state title="No history yet" message="Configuration changes will be recorded here." icon="document" />
        @endforelse
    </div>
    <div class="card-footer">{{ $histories->links() }}</div>
</div>
<a href="{{ route('admin.settings.index') }}" class="btn-secondary mt-4">Back to settings</a>
@endsection
