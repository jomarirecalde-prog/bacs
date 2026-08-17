@extends('layouts.app')

@section('title', $department->name)
@section('page-title', $department->name)
@section('page-subtitle', $department->description)

@section('content')
<div class="flex justify-between items-start mb-4">
    <div>
        <p class="text-sm text-slate-500">Status: {{ $department->status?->label() }}</p>
    </div>
    @if (auth()->user()->isAdmin())
        <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="card p-4 flex flex-wrap gap-2 items-end">
            @csrf @method('PUT')
            <div><label class="label">Name</label><input class="input" name="name" value="{{ $department->name }}"></div>
            <div><label class="label">Description</label><input class="input" name="description" value="{{ $department->description }}"></div>
            <div>
                <label class="label">Status</label>
                <select name="status" class="input">
                    <option value="active" @selected($department->status?->value === 'active')>Active</option>
                    <option value="inactive" @selected($department->status?->value === 'inactive')>Inactive</option>
                </select>
            </div>
            <button class="btn-primary">Update</button>
        </form>
    @endif
</div>
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b font-bold">Employees in this department</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Name</th><th>ID No.</th><th>Position</th><th>Account</th></tr></thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td><a class="font-semibold text-brand-800" href="{{ route('admin.employees.show', $employee) }}">{{ $employee->fullName() }}</a></td>
                        <td>{{ $employee->employee_number }}</td>
                        <td>{{ $employee->position }}</td>
                        <td>{{ $employee->user?->status?->label() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No employees" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3">{{ $employees->links() }}</div>
</div>
@endsection
