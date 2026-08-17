@extends('layouts.app')

@section('title', 'Audit Logs')
@section('page-title', 'Audit Logs')

@section('content')
<form class="card p-4 flex flex-wrap gap-2 mb-6">
    <input name="q" value="{{ request('q') }}" class="input max-w-sm" placeholder="Search action, module, description">
    <select name="module" class="input max-w-xs">
        <option value="">All modules</option>
        @foreach ($modules as $module)
            <option value="{{ $module }}" @selected(request('module') === $module)>{{ $module }}</option>
        @endforeach
    </select>
    <button class="btn-primary">Filter</button>
</form>
<div class="card overflow-hidden">
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Module</th><th>Record</th><th>Description</th><th>IP</th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap">{{ $log->created_at?->format('M d, Y g:i A') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td class="font-semibold">{{ str_replace('_', ' ', $log->action) }}</td>
                        <td>{{ $log->module }}</td>
                        <td>{{ $log->record_id ?? '—' }}</td>
                        <td>{{ $log->description }}</td>
                        <td class="text-xs">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state title="No audit logs" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $logs->links() }}</div>
</div>
@endsection
