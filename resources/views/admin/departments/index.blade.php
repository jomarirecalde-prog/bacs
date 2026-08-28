@extends('layouts.app')

@section('title', 'Departments')
@section('page-title', 'Departments')
@section('page-subtitle', 'Organizational units and headcount')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    @if (auth()->user()->isAdmin())
        <div class="card card-accent-brand overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Add department</h2>
            </div>
            <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-4 p-5">
                @csrf
                <div>
                    <label class="label" for="dept-name">Name</label>
                    <input id="dept-name" class="input @error('name') input-error @enderror" name="name" required>
                    @error('name')<p class="error-text">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="label" for="dept-description">Description</label>
                    <textarea id="dept-description" class="textarea" name="description" rows="3"></textarea>
                </div>
                <button type="submit" class="btn-primary btn-block">Save department</button>
            </form>
        </div>
    @endif

    <div class="{{ auth()->user()->isAdmin() ? 'lg:col-span-2' : 'lg:col-span-3' }} card overflow-hidden">
        <form class="border-b border-line p-4">
            <div class="relative">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input name="q" value="{{ request('q') }}" class="input pl-9" placeholder="Search departments">
            </div>
        </form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th class="text-right">Employees</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($departments as $department)
                        <tr>
                            <td>
                                <div class="font-semibold text-ink">{{ $department->name }}</div>
                                @if ($department->description)
                                    <div class="text-xs text-muted">{{ $department->description }}</div>
                                @endif
                            </td>
                            <td class="text-right font-semibold text-ink tabular-nums">{{ $department->employees_count }}</td>
                            <td>
                                <span class="{{ $department->status?->value === 'active' ? 'badge-brand' : 'badge-neutral' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $department->status?->label() }}
                                </span>
                            </td>
                            <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('admin.departments.show', $department) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-0"><x-empty-state title="No departments" message="Create a department to group employees and report on headcount." icon="building" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $departments->links() }}</div>
    </div>
</div>
@endsection
