@extends('layouts.app')

@section('title', $employee->fullName())
@section('page-title', $employee->fullName())
@section('page-subtitle', $employee->employee_number.' · '.$employee->department?->name)

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card card-accent-brand overflow-hidden">
        <div class="p-6">
            <div class="flex items-start gap-4">
                <img src="{{ $employee->photoUrl() }}" alt="{{ $employee->fullName() }}" class="h-24 w-24 shrink-0 rounded-2xl object-cover ring-2 ring-brand-100">
                <div class="min-w-0 pt-1">
                    <h2 class="text-xl font-extrabold tracking-tight text-ink">{{ $employee->fullName() }}</h2>
                    <p class="mt-0.5 text-sm text-muted">{{ $employee->position }}</p>
                    @if ($employee->employment_status)
                        <span class="badge-gold mt-2">{{ $employee->employment_status->label() }}</span>
                    @endif
                </div>
            </div>

            <dl class="mt-6 divide-y divide-line text-sm">
                @foreach ([
                    ['Employee Number', $employee->employee_number],
                    ['Employee Name', $employee->fullName()],
                    ['Department', $employee->department?->name ?? '—'],
                    ['Position', $employee->position ?? '—'],
                    ['Employment Status', $employee->employment_status?->label()],
                    ['Account Status', $employee->account_status?->label()],
                    ['Username', $employee->user?->username],
                    ['Email', $employee->email],
                ] as [$term, $definition])
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="shrink-0 text-muted">{{ $term }}</dt>
                        <dd class="text-right font-semibold text-ink">{{ $definition ?: '—' }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>

        <div class="card-footer flex flex-wrap gap-2">
            <a class="btn-primary btn-sm" href="{{ route('admin.dtr.monthly', ['employee_id' => $employee->id]) }}">View Complete DTR</a>
            @if (auth()->user()->canManageEmployees())
                <a class="btn-outline-info btn-sm" href="{{ route('admin.employees.edit', $employee) }}">Edit</a>
            @endif
            @if (auth()->user()->isAdmin())
                <a class="btn-gold btn-sm" href="{{ route('admin.employees.qr', $employee) }}">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2z"/></svg>
                    QR Code
                </a>
            @endif
            @if (auth()->user()->canManageEmployees())
                @if ($employee->user?->status?->value === 'active')
                    <form method="POST" action="{{ route('admin.employees.deactivate', $employee) }}" onsubmit="return confirm('Deactivate this employee? Historical DTR will be kept.')">
                        @csrf
                        <button type="submit" class="btn-outline-danger btn-sm">Deactivate</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('admin.employees.activate', $employee) }}">
                        @csrf
                        <button type="submit" class="btn-primary btn-sm">Activate</button>
                    </form>
                @endif
            @endif
        </div>
    </div>

    <div class="space-y-6 lg:col-span-2">
        <div class="card overflow-hidden">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Attendance Summary</h3>
                    <p class="mt-0.5 text-xs text-muted">Current month · {{ \Carbon\Carbon::create($summary['year'], $summary['month'], 1)->format('F Y') }}</p>
                </div>
            </div>
            <div class="grid gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4">
                <x-stat-card label="Days Present" :value="$summary['present']" tone="green" icon="check" />
                <x-stat-card label="Days Late" :value="$summary['late']" tone="yellow" icon="clock" />
                <x-stat-card label="Days Absent" :value="$summary['absent']" tone="red" icon="x" />
                <x-stat-card label="Missing Time Outs" :value="$summary['missing_timeout']" tone="yellow" icon="warning" />
                <x-stat-card label="Total Late Minutes" :value="$summary['late_minutes']" tone="warn" />
                <x-stat-card label="Total Undertime" :value="$summary['undertime_minutes']" tone="info" />
                <x-stat-card label="Total Overtime" :value="$summary['overtime_minutes']" tone="gold" />
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">Recent DTR</h3>
                <a class="link text-xs" href="{{ route('admin.dtr.monthly', ['employee_id' => $employee->id]) }}">View Complete DTR</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>In</th><th>Out</th><th>Hours</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($employee->attendance as $row)
                            <tr>
                                <td class="font-medium text-ink">{{ $row->attendance_date->toFormattedDateString() }}</td>
                                <td class="font-medium text-brand-700 tabular-nums">{{ $row->time_in?->format('g:i A') ?? '—' }}</td>
                                <td class="font-medium text-info-700 tabular-nums">{{ $row->time_out?->format('g:i A') ?? '—' }}</td>
                                <td class="tabular-nums">{{ $row->totalHoursLabel() }}</td>
                                <td><x-status-badge :status="$row->status" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="p-0"><x-empty-state title="No recent DTR" message="This employee has no stored attendance records yet." icon="clock" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
