@extends('layouts.app')

@section('title', 'Designations')
@section('page-title', 'Designation master')
@section('page-subtitle', 'Manual default compensation by position — employee salary is assigned separately')

@section('content')
@if (session('success'))
    <div class="alert-success mb-4 text-sm">{{ session('success') }}</div>
@endif

<div class="mb-4 flex flex-wrap items-center justify-between gap-3">
    @if (auth()->user()->canManagePayroll())
        <a href="{{ route('admin.designations.create') }}" class="btn-primary btn-sm">Add designation</a>
    @endif
    <form class="flex flex-1 justify-end">
        <input name="q" value="{{ request('q') }}" class="input w-full max-w-xs" placeholder="Search designations">
    </form>
</div>

<div class="card overflow-hidden">
    <div class="table-wrap overflow-x-auto">
        <table class="data-table min-w-[960px] text-sm">
            <thead>
                <tr>
                    <th>Designation</th>
                    <th>Department</th>
                    <th>Salary type</th>
                    <th class="text-right">Daily rate</th>
                    <th class="text-right">Hourly rate</th>
                    <th class="text-right">Hrs/day</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($designations as $designation)
                    <tr>
                        <td>
                            <div class="font-semibold text-ink">{{ $designation->designation_name }}</div>
                            <div class="text-xs font-mono text-muted">{{ $designation->designation_code }}</div>
                        </td>
                        <td>{{ $designation->department?->name ?? '—' }}</td>
                        <td>{{ $designation->default_pay_type?->label() ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $designation->default_daily_rate ? '₱'.number_format($designation->default_daily_rate, 2) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $designation->default_hourly_rate ? '₱'.number_format($designation->default_hourly_rate, 4) : '—' }}</td>
                        <td class="text-right tabular-nums">{{ $designation->default_working_hours_per_day ?? '—' }}</td>
                        <td>
                            <span class="{{ $designation->is_active ? 'badge-brand' : 'badge-neutral' }}">
                                {{ $designation->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td class="text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                <a class="btn-outline btn-sm" href="{{ route('admin.designations.show', $designation) }}">View</a>
                                @if (auth()->user()->canManagePayroll())
                                    <a class="btn-outline btn-sm" href="{{ route('admin.designations.edit', $designation) }}">Edit</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="p-0">
                            <x-empty-state title="No designations" message="Add designations manually or run the designation seeder." icon="users" />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($designations->hasPages())
        <div class="border-t border-line p-4">{{ $designations->links() }}</div>
    @endif
</div>
@endsection
