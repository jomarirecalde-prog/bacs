@extends('layouts.app')

@section('title', 'Employees')
@section('page-title', 'Employees')
@section('page-subtitle', 'Manage employee accounts and assignments')

@section('content')
<div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
    <form class="flex flex-1 flex-wrap gap-2">
        <input name="q" value="{{ $filters['q'] ?? '' }}" class="input max-w-xs" placeholder="Search name, number, department, position">
        <select name="department_id" class="input max-w-xs">
            <option value="">All departments</option>
            @foreach ($departments as $dept)
                <option value="{{ $dept->id }}" @selected(($filters['department_id'] ?? '') == $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>
        <select name="status" class="input max-w-[160px]">
            <option value="">All statuses</option>
            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
            <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
        </select>
        <button class="btn-secondary">Search</button>
    </form>
    @if (auth()->user()->canManageEmployees())
        <a href="{{ route('admin.employees.create') }}" class="btn-primary">Add employee</a>
    @endif
</div>

<div class="mt-6 card overflow-hidden">
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
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td>
                            <div class="flex items-center gap-3">
                                <img src="{{ $employee->photoUrl() }}" alt="" class="h-9 w-9 rounded-full object-cover">
                                <div>
                                    <div class="font-semibold">{{ $employee->fullName() }}</div>
                                    <div class="text-xs text-slate-500">{{ $employee->position }} · {{ $employee->department?->name }}</div>
                                </div>
                            </div>
                        </td>
                        <td>{{ $employee->employee_number }}</td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td>{{ $employee->position ?? '—' }}</td>
                        <td>{{ $employee->user?->role?->label() }}</td>
                        <td>
                            <span class="text-xs font-semibold uppercase {{ $employee->user?->status?->value === 'active' ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ $employee->user?->status?->label() }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a class="text-sm font-semibold text-brand-700" href="{{ route('admin.employees.show', $employee) }}">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty-state title="No employees" message="Add your first employee to start monitoring DTR." /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $employees->links() }}</div>
</div>
@endsection
