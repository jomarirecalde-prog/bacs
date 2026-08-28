@extends('layouts.app')

@section('title', 'Employees')
@section('page-title', 'Employees')
@section('page-subtitle', 'Manage employee accounts and assignments')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <form class="filter-bar flex-1">
            <div class="min-w-[14rem] flex-[2]">
                <label class="label" for="emp-q">Search</label>
                <input id="emp-q" name="q" value="{{ $filters['q'] ?? '' }}" class="input" placeholder="Name, number, department, position">
            </div>
            <div class="min-w-[11rem] flex-1">
                <label class="label" for="emp-dept">Department</label>
                <select id="emp-dept" name="department_id" class="select">
                    <option value="">All departments</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="min-w-[9rem] flex-1">
                <label class="label" for="emp-status">Status</label>
                <select id="emp-status" name="status" class="select">
                    <option value="">All statuses</option>
                    <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                    <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                    <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
                </select>
            </div>
            <button type="submit" class="btn-secondary">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                Search
            </button>
        </form>

        @if (auth()->user()->canManageEmployees())
            <a href="{{ route('admin.employees.create') }}" class="btn-primary shrink-0">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add employee
            </a>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>ID No.</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td>
                                <div class="flex items-center gap-3">
                                    <img src="{{ $employee->photoUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-brand-100">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-ink">{{ $employee->fullName() }}</div>
                                        <div class="truncate text-xs text-muted">{{ $employee->position }} · {{ $employee->department?->name }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="font-medium tabular-nums">{{ $employee->employee_number }}</td>
                            <td>{{ $employee->department?->name ?? '—' }}</td>
                            <td>{{ $employee->position ?? '—' }}</td>
                            <td>
                                @if ($employee->user?->role)
                                    <span class="badge-info">{{ $employee->user->role->label() }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                @php $accountStatus = $employee->user?->status?->value; @endphp
                                <span class="{{ $accountStatus === 'active' ? 'badge-brand' : ($accountStatus === 'suspended' ? 'badge-warn' : 'badge-critical') }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $employee->user?->status?->label() }}
                                </span>
                            </td>
                            <td class="text-right">
                                <a class="btn-outline btn-sm" href="{{ route('admin.employees.show', $employee) }}">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="p-0"><x-empty-state title="No employees" message="Add your first employee to start monitoring DTR." icon="users" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $employees->links() }}</div>
    </div>
</div>
@endsection
