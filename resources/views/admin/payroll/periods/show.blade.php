@extends('layouts.app')

@section('title', $period->period_name)
@section('page-title', $period->period_name)
@section('page-subtitle', $period->start_date->format('M j, Y').' – '.$period->end_date->format('M j, Y').' · '.$period->status?->label())

@section('content')
<div class="mb-6 flex flex-wrap gap-2">
    @can('compute', $period)
    @if ($period->status?->allowsRecomputation())
        <form method="POST" action="{{ route('admin.payroll.periods.compute', $period) }}">
            @csrf
            <button type="submit" class="btn-outline btn-sm">Compute attendance only</button>
        </form>
        <form method="POST" action="{{ route('admin.payroll.periods.compute-payroll', $period) }}">
            @csrf
            <button type="submit" class="btn-primary btn-sm">Compute full payroll</button>
        </form>
    @endif
    @endcan
    @if (($period->payroll_employees_count ?? 0) > 0)
        <a href="{{ route('admin.payroll.register.index', $period) }}" class="btn-gold btn-sm">Payroll register</a>
    @endif
    @if (! $period->isLocked())
        <a href="{{ route('admin.payroll.overtime.index', $period) }}" class="btn-outline btn-sm">Overtime approval</a>
        <a href="{{ route('admin.payroll.adjustments.index', $period) }}" class="btn-outline btn-sm">Adjustments</a>
    @endif

    @if ($period->status === \App\Enums\PayrollPeriodStatus::Finalized)
        <form method="POST" action="{{ route('admin.payroll.periods.status', $period) }}">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="paid">
            <button type="submit" class="btn-gold btn-sm">Mark as paid</button>
        </form>
    @endif

    @if (in_array($period->status, [\App\Enums\PayrollPeriodStatus::Finalized, \App\Enums\PayrollPeriodStatus::Paid], true))
        <form method="POST" action="{{ route('admin.payroll.periods.notify-payslips', $period) }}" onsubmit="return confirm('Email payslip links to all employees with an address on file?');">
            @csrf
            <button type="submit" class="btn-outline btn-sm">Email payslips</button>
        </form>
    @endif

    @if (! $period->isLocked())
        <form method="POST" action="{{ route('admin.payroll.periods.status', $period) }}" class="flex flex-wrap items-center gap-2">
            @csrf @method('PUT')
            <select name="status" class="select w-auto text-sm">
                @foreach (\App\Enums\PayrollPeriodStatus::cases() as $status)
                    <option value="{{ $status->value }}" @selected($period->status === $status)>{{ $status->label() }}</option>
                @endforeach
            </select>
            @if (($finalizeCheck['warnings'] ?? []) !== [])
                <label class="flex items-center gap-2 text-xs text-muted">
                    <input type="checkbox" name="acknowledge_warnings" value="1" class="checkbox">
                    Acknowledge {{ count($finalizeCheck['warnings']) }} warning(s) if finalizing
                </label>
            @endif
            <button type="submit" class="btn-outline btn-sm">Update status</button>
        </form>
    @endif

    @if ($period->status === \App\Enums\PayrollPeriodStatus::Approved && ($finalizeCheck['can_finalize'] ?? false))
        <form method="POST" action="{{ route('admin.payroll.periods.status', $period) }}" class="flex flex-wrap items-center gap-2">
            @csrf @method('PUT')
            <input type="hidden" name="status" value="finalized">
            @if (($finalizeCheck['warnings'] ?? []) !== [])
                <label class="flex items-center gap-2 text-xs">
                    <input type="checkbox" name="acknowledge_warnings" value="1" class="checkbox" required>
                    I reviewed {{ count($finalizeCheck['warnings']) }} warning(s)
                </label>
            @endif
            <button type="submit" class="btn-primary btn-sm">Finalize payroll</button>
        </form>
    @endif
</div>

@if ($period->isLocked())
    <div class="alert-warning mb-4 text-sm">This payroll period is finalized. Historical summaries cannot be recomputed.</div>
@endif

@if (! $period->isLocked() && ($period->payroll_employees_count ?? 0) > 0)
    <div class="card mb-6 overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Pre-finalize checklist</h2>
            @if ($finalizeCheck['can_finalize'] ?? false)
                <span class="badge-brand">Ready</span>
            @else
                <span class="badge-neutral">Blocked</span>
            @endif
        </div>
        <div class="space-y-3 p-5 text-sm">
            @forelse ($finalizeCheck['blocking'] ?? [] as $item)
                <div class="flex gap-2 text-critical-700">
                    <span aria-hidden="true">✕</span>
                    <span>{{ $item['message'] }}</span>
                </div>
            @empty
                <div class="text-muted">No blocking issues.</div>
            @endforelse
            @foreach ($finalizeCheck['warnings'] ?? [] as $item)
                <div class="flex gap-2 text-warn-700">
                    <span aria-hidden="true">!</span>
                    <span>{{ $item['message'] }}</span>
                </div>
            @endforeach
            @if ($period->status !== \App\Enums\PayrollPeriodStatus::Approved)
                <p class="text-xs text-muted">Move status to <strong>Approved</strong> before finalizing.</p>
            @endif
        </div>
    </div>
@endif

<div class="card overflow-hidden">
    <div class="card-header">
        <h2 class="card-title">DTR attendance summary</h2>
        <span class="chip">{{ $summaries->total() }} employees</span>
    </div>
    <div class="table-wrap">
        <table class="data-table text-sm">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Designation</th>
                    <th class="text-right">Scheduled</th>
                    <th class="text-right">Absent</th>
                    <th class="text-right">Late (min)</th>
                    <th class="text-right">UT (min)</th>
                    <th class="text-right">OT (hrs)</th>
                    <th>Warnings</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summaries as $row)
                    <tr>
                        <td>
                            <div class="font-semibold text-ink">{{ $row->employee?->full_name }}</div>
                            <div class="text-xs text-muted">{{ $row->employee?->employee_number }}</div>
                        </td>
                        <td>{{ $row->employee?->designation?->designation_name ?? $row->employee?->position ?? '—' }}</td>
                        <td class="text-right tabular-nums">{{ $row->scheduled_days }}</td>
                        <td class="text-right tabular-nums">{{ $row->absent_days }}</td>
                        <td class="text-right tabular-nums">{{ $row->late_minutes }}</td>
                        <td class="text-right tabular-nums">{{ $row->undertime_minutes }}</td>
                        <td class="text-right tabular-nums">{{ $row->recorded_ot_hours }}</td>
                        <td class="text-xs text-warn-700">
                            @if ($row->warnings)
                                {{ implode('; ', $row->warnings) }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-0"><x-empty-state title="No summaries yet" message="Run Compute attendance summary to pull DTR data for this period." icon="clock" /></td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($summaries->hasPages())
        <div class="border-t border-line p-4">{{ $summaries->links() }}</div>
    @endif
</div>
@endsection
