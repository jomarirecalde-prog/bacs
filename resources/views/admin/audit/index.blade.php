@extends('layouts.app')

@section('title', 'Audit Logs')
@section('page-title', 'Audit Logs')
@section('page-subtitle', 'Immutable trail of system activity')

@section('content')
<div class="space-y-6">
    <form class="filter-bar">
        <div class="min-w-[14rem] flex-[2]">
            <label class="label" for="audit-q">Search</label>
            <input id="audit-q" name="q" value="{{ request('q') }}" class="input" placeholder="Action, module, description">
        </div>
        <div class="min-w-[11rem] flex-1">
            <label class="label" for="audit-module">Module</label>
            <select id="audit-module" name="module" class="select">
                <option value="">All modules</option>
                @foreach ($modules as $module)
                    <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Filter</button>
    </form>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th><th>IP</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td class="whitespace-nowrap text-muted">{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                            <td class="font-medium text-ink">{{ $log->user?->name ?? 'System' }}</td>
                            <td><span class="badge-info">{{ str_replace('_', ' ', $log->action) }}</span></td>
                            <td><span class="chip">{{ $log->module }}</span></td>
                            <td class="text-muted tabular-nums">{{ $log->record_id ?? '—' }}</td>
                            <td>{{ $log->description }}</td>
                            <td class="text-xs text-muted tabular-nums">{{ $log->ip_address }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No audit logs" message="System activity will appear here as users make changes." icon="shield" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $logs->links() }}</div>
    </div>
</div>
@endsection
