@extends('layouts.app')

@section('title', $designation->designation_name)
@section('page-title', $designation->designation_name)
@section('page-subtitle', $designation->designation_code.' · Default compensation profile')

@section('content')
@if (session('success'))
    <div class="alert-success mb-4 text-sm">{{ session('success') }}</div>
@endif

<div class="mb-4 flex flex-wrap items-center gap-2">
    <a href="{{ route('admin.designations.index') }}" class="btn-outline btn-sm">← All designations</a>
    @if (auth()->user()->canManagePayroll())
        <a href="{{ route('admin.designations.edit', $designation) }}" class="btn-primary btn-sm">Edit / salary configuration</a>
    @endif
    <span class="{{ $designation->is_active ? 'badge-brand' : 'badge-neutral' }}">
        {{ $designation->is_active ? 'Active' : 'Inactive' }}
    </span>
    <span class="chip">{{ $employees->total() }} employees</span>
</div>

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Profile</h2></div>
        <dl class="divide-y divide-line p-5 text-sm">
            @foreach ([
                ['Department', $designation->department?->name ?? '—'],
                ['Salary type (default)', $designation->default_pay_type?->label() ?? '—'],
                ['Working hours / day', $designation->default_working_hours_per_day ?? '—'],
                ['Working days / period', $designation->default_working_days_per_period ?? '—'],
                ['Description', $designation->description ?: '—'],
            ] as [$label, $value])
                <div class="flex justify-between gap-4 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-semibold text-ink">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Default rates (manual config)</h2></div>
        <dl class="divide-y divide-line p-5 text-sm">
            @foreach ([
                ['Basic salary', $designation->default_basic_salary],
                ['Semi-monthly', $designation->default_semi_monthly_salary],
                ['Daily rate', $designation->default_daily_rate],
                ['Hourly rate', $designation->default_hourly_rate],
            ] as [$label, $amount])
                <div class="flex justify-between gap-4 py-2">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="tabular-nums font-semibold text-ink">
                        {{ $amount !== null ? '₱'.number_format($amount, $label === 'Hourly rate' ? 4 : 2) : '—' }}
                    </dd>
                </div>
            @endforeach
        </dl>
        <p class="border-t border-line px-5 py-3 text-xs text-muted">
            Assign actual employee pay under Employee → Salary history; defaults are prefill suggestions only.
        </p>
    </div>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="card-header"><h2 class="card-title">Employees with this designation</h2></div>
    <div class="table-wrap">
        <table class="data-table text-sm">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Employee #</th>
                    <th>Department</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($employees as $employee)
                    <tr>
                        <td class="font-semibold text-ink">{{ $employee->fullName() }}</td>
                        <td class="font-mono text-muted">{{ $employee->employee_number }}</td>
                        <td>{{ $employee->department?->name ?? '—' }}</td>
                        <td class="text-right">
                            <a class="btn-outline btn-sm" href="{{ route('admin.employees.show', $employee) }}">Profile</a>
                            @if (auth()->user()->canManagePayroll())
                                <a class="btn-outline btn-sm" href="{{ route('admin.payroll.employees.salary.index', $employee) }}">Salary</a>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-0"><x-empty-state title="No employees" message="No active employees use this designation yet." icon="users" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($employees->hasPages())
        <div class="border-t border-line p-4">{{ $employees->links() }}</div>
    @endif
</div>
@endsection
