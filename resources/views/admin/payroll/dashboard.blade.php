@extends('layouts.app')

@section('title', 'Payroll')
@section('page-title', 'Payroll Management')
@section('page-subtitle', 'Periods, designations, and DTR-based attendance summaries')

@section('content')
<div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <x-stat-card label="Active employees" :value="$stats['employees']" tone="brand" />
    <x-stat-card label="Payroll periods" :value="$stats['periods']" tone="neutral" />
    <x-stat-card label="Latest net payroll" :value="'₱'.number_format($stats['net_payroll'], 2)" tone="gold" />
    <x-stat-card label="Pending review" :value="$stats['pending_review']" tone="gold" />
    <x-stat-card label="Finalized" :value="$stats['finalized']" tone="green" />
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="card card-accent-brand lg:col-span-2 overflow-hidden">
        <div class="card-header flex flex-wrap items-center justify-between gap-2">
            <h2 class="card-title">Current focus</h2>
            <a href="{{ route('admin.payroll.periods.create') }}" class="btn-primary btn-sm">New payroll period</a>
        </div>
        <div class="space-y-4 p-5">
            @if ($currentPeriod)
                <div>
                    <div class="text-lg font-bold text-ink">{{ $currentPeriod->period_name }}</div>
                    <div class="text-sm text-muted">
                        {{ $currentPeriod->start_date->toFormattedDateString() }} – {{ $currentPeriod->end_date->toFormattedDateString() }}
                        · <span class="badge-neutral">{{ $currentPeriod->status?->label() }}</span>
                    </div>
                </div>
                <a class="btn-outline btn-sm" href="{{ route('admin.payroll.periods.show', $currentPeriod) }}">Open period</a>
            @else
                <x-empty-state title="No payroll period yet" message="Create a payroll period aligned with your DTR cut-off (11–25 or 26–10)." icon="document" />
            @endif
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Quick links</h2>
        </div>
        <div class="space-y-2 p-5">
            <a class="btn-outline btn-block justify-start" href="{{ route('admin.payroll.periods.index') }}">Payroll periods</a>
            <a class="btn-outline btn-block justify-start" href="{{ route('admin.designations.index') }}">Designation master</a>
            <a class="btn-outline btn-block justify-start" href="{{ route('admin.payroll.settings.index') }}">Payroll configuration</a>
        </div>
    </div>
</div>

<div class="card mt-6 overflow-hidden">
    <div class="card-header">
        <h2 class="card-title">Recent periods</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Period</th>
                    <th>Cover dates</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($recentPeriods as $period)
                    <tr>
                        <td class="font-semibold text-ink">{{ $period->period_name }}</td>
                        <td class="text-sm text-muted">{{ $period->start_date->format('M j, Y') }} – {{ $period->end_date->format('M j, Y') }}</td>
                        <td><span class="badge-neutral">{{ $period->status?->label() }}</span></td>
                        <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('admin.payroll.periods.show', $period) }}">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="p-0"><x-empty-state title="No periods" message="Create your first payroll period to begin." icon="calendar" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
