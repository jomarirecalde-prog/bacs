@extends('layouts.app')

@section('title', $employee->fullName())
@section('page-title', $employee->fullName())
@section('page-subtitle', $employee->employee_number.' · '.$employee->department?->name)

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-6">
        <img src="{{ $employee->photoUrl() }}" alt="{{ $employee->fullName() }}" class="h-24 w-24 rounded-2xl object-cover">
        <h2 class="mt-4 text-xl font-bold">{{ $employee->fullName() }}</h2>
        <p class="text-sm text-slate-500">{{ $employee->position }}</p>
        <dl class="mt-4 space-y-2 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Employee Number</dt><dd class="font-semibold">{{ $employee->employee_number }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Employee Name</dt><dd class="text-right">{{ $employee->fullName() }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Department</dt><dd class="text-right">{{ $employee->department?->name ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Position</dt><dd class="text-right">{{ $employee->position ?? '—' }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Employment Status</dt><dd>{{ $employee->employment_status?->label() }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Account Status</dt><dd>{{ $employee->account_status?->label() }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Username</dt><dd>{{ $employee->user?->username }}</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Email</dt><dd class="text-right">{{ $employee->email }}</dd></div>
        </dl>
        <div class="mt-6 flex flex-wrap gap-2">
            @if (auth()->user()->canManageEmployees())
                <a class="btn-secondary" href="{{ route('admin.employees.edit', $employee) }}">Edit</a>
                @if ($employee->user?->status?->value === 'active')
                    <form method="POST" action="{{ route('admin.employees.deactivate', $employee) }}" onsubmit="return confirm('Deactivate this employee? Historical DTR will be kept.')">
                        @csrf
                        <button class="btn-danger">Deactivate</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.employees.activate', $employee) }}">
                        @csrf
                        <button class="btn-primary">Activate</button>
                    </form>
                @endif
            @endif
            <a class="btn-primary" href="{{ route('admin.dtr.monthly', ['employee_id' => $employee->id]) }}">View Complete DTR</a>
            @if (auth()->user()->isAdmin())
                <a class="btn-secondary" href="{{ route('admin.employees.qr', $employee) }}">QR Code</a>
            @endif
        </div>
    </div>
    <div class="lg:col-span-2 space-y-6">
        <div class="card p-6">
            <h3 class="font-bold">Attendance Summary</h3>
            <p class="text-sm text-slate-500">Current month · {{ \Carbon\Carbon::create($summary['year'], $summary['month'], 1)->format('F Y') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Days Present" :value="$summary['present']" tone="green" />
                <x-stat-card label="Days Late" :value="$summary['late']" tone="orange" />
                <x-stat-card label="Days Absent" :value="$summary['absent']" tone="red" />
                <x-stat-card label="Missing Time Outs" :value="$summary['missing_timeout']" tone="yellow" />
                <x-stat-card label="Total Late Minutes" :value="$summary['late_minutes']" />
                <x-stat-card label="Total Undertime" :value="$summary['undertime_minutes']" />
                <x-stat-card label="Total Overtime" :value="$summary['overtime_minutes']" />
            </div>
        </div>
        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b flex items-center justify-between">
                <h3 class="font-bold">Recent DTR</h3>
                <a class="text-sm font-semibold text-brand-700" href="{{ route('admin.dtr.monthly', ['employee_id' => $employee->id]) }}">View Complete DTR</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>In</th><th>Out</th><th>Hours</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($employee->attendance as $row)
                            <tr>
                                <td>{{ $row->attendance_date->toFormattedDateString() }}</td>
                                <td>{{ $row->time_in?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $row->time_out?->format('g:i A') ?? '—' }}</td>
                                <td>{{ $row->totalHoursLabel() }}</td>
                                <td><x-status-badge :status="$row->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty-state title="No recent DTR" message="This employee has no stored attendance records yet." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
