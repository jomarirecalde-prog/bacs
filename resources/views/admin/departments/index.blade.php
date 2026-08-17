@extends('layouts.app')

@section('title', 'Departments')
@section('page-title', 'Departments')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    @if (auth()->user()->isAdmin())
    <div class="card p-6">
        <h2 class="font-bold mb-4">Add department</h2>
        <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-3">
            @csrf
            <div><label class="label">Name</label><input class="input" name="name" required></div>
            <div><label class="label">Description</label><textarea class="input" name="description" rows="3"></textarea></div>
            <button class="btn-primary">Save</button>
        </form>
    </div>
    @endif
    <div class="{{ auth()->user()->isAdmin() ? 'lg:col-span-2' : 'lg:col-span-3' }} card overflow-hidden">
        <form class="p-4 border-b"><input name="q" value="{{ request('q') }}" class="input" placeholder="Search departments"></form>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Employees</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($departments as $department)
                        <tr>
                            <td>
                                <div class="font-semibold">{{ $department->name }}</div>
                                <div class="text-xs text-slate-500">{{ $department->description }}</div>
                            </td>
                            <td>{{ $department->employees_count }}</td>
                            <td>{{ $department->status?->label() }}</td>
                            <td class="text-right"><a class="text-sm font-semibold text-brand-700" href="{{ route('admin.departments.show', $department) }}">View</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-5 py-3">{{ $departments->links() }}</div>
    </div>
</div>
@endsection
