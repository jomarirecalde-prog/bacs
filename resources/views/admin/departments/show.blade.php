@extends('layouts.app')

@section('title', $department->name)
@section('page-title', $department->name)
@section('page-subtitle', $department->description)

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.departments.index') }}" class="btn-outline btn-sm">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                All departments
            </a>
            <span class="{{ $department->status?->value === 'active' ? 'badge-brand' : 'badge-neutral' }}">
                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                {{ $department->status?->label() }}
            </span>
        </div>
        <span class="chip">{{ $employees->total() }} employees</span>
    </div>

    @if (auth()->user()->isAdmin())
        <div class="card card-accent-info overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Department Settings</h2>
            </div>
            <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="p-5">
                @csrf @method('PUT')
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="label" for="dept-name">Name</label>
                        <input id="dept-name" class="input" name="name" value="{{ $department->name }}">
                    </div>
                    <div>
                        <label class="label" for="dept-description">Description</label>
                        <input id="dept-description" class="input" name="description" value="{{ $department->description }}">
                    </div>
                    <div>
                        <label class="label" for="dept-status">Status</label>
                        <select id="dept-status" name="status" class="select">
                            <option value="active" @selected($department->status?->value === 'active')>Active</option>
                            <option value="inactive" @selected($department->status?->value === 'inactive')>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn-primary">Update department</button>
                </div>
            </form>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Employees in this department</h2>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>ID No.</th><th>Position</th><th>Account</th></tr></thead>
                <tbody>
                    @forelse ($employees as $employee)
                        <tr>
                            <td><a class="font-semibold text-ink transition hover:text-brand-700" href="{{ route('admin.employees.show', $employee) }}">{{ $employee->fullName() }}</a></td>
                            <td class="tabular-nums">{{ $employee->employee_number }}</td>
                            <td>{{ $employee->position ?? '—' }}</td>
                            <td>
                                @php $accountStatus = $employee->user?->status?->value; @endphp
                                <span class="{{ $accountStatus === 'active' ? 'badge-brand' : ($accountStatus === 'suspended' ? 'badge-warn' : 'badge-neutral') }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                    {{ $employee->user?->status?->label() ?? '—' }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="p-0"><x-empty-state title="No employees" message="No employees are assigned to this department yet." icon="users" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $employees->links() }}</div>
    </div>
</div>
@endsection
