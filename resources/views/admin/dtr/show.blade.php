@extends('layouts.app')

@section('title', 'DTR Record')
@section('page-title', 'DTR Record')
@section('page-subtitle', $attendance->employee?->fullName().' · '.$attendance->attendance_date->toFormattedDateString())

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card card-accent-brand h-fit overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Record Details</h2>
            <x-status-badge :status="$attendance->status" />
        </div>

        <dl class="divide-y divide-line px-5 text-sm">
            <div class="flex justify-between gap-4 py-2.5">
                <dt class="text-muted">Employee</dt>
                <dd class="text-right font-semibold text-ink">{{ $attendance->employee?->fullName() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-2.5">
                <dt class="text-muted">Department</dt>
                <dd class="text-right">{{ $attendance->employee?->department?->name ?? '—' }}</dd>
            </div>
            @foreach ([
                'AM Time In' => $attendance->am_time_in,
                'AM Time Out' => $attendance->am_time_out,
                'PM Time In' => $attendance->pm_time_in,
                'PM Time Out' => $attendance->pm_time_out,
                'Overtime' => $attendance->overtime_in,
            ] as $label => $value)
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="text-right font-bold text-ink tabular-nums">{{ $value?->format('h:i A') ?? '—' }}</dd>
                </div>
            @endforeach
            <div class="flex justify-between gap-4 py-2.5">
                <dt class="text-muted">Regular Hours</dt>
                <dd class="text-right font-semibold text-ink tabular-nums">{{ $attendance->totalHoursLabel() }}</dd>
            </div>
            <div class="flex justify-between gap-4 py-2.5">
                <dt class="text-muted">Late</dt>
                <dd class="text-right tabular-nums {{ $attendance->late_minutes > 0 ? 'font-bold text-warn-700' : '' }}">{{ $attendance->late_minutes }} min</dd>
            </div>
            <div class="flex justify-between gap-4 py-2.5">
                <dt class="text-muted">Undertime</dt>
                <dd class="text-right tabular-nums {{ $attendance->undertime_minutes > 0 ? 'font-bold text-warn-700' : '' }}">{{ $attendance->undertime_minutes }} min</dd>
            </div>
            <div class="flex justify-between gap-4 py-2.5">
                <dt class="text-muted">Overtime Hours</dt>
                <dd class="text-right tabular-nums {{ $attendance->overtime_minutes > 0 ? 'font-bold text-gold-700' : '' }}">{{ $attendance->overtimeHoursLabel() }}</dd>
            </div>
            <div class="py-3">
                <dt class="text-muted">Remarks</dt>
                <dd class="mt-1 text-ink">{{ $attendance->remarks ?: '—' }}</dd>
            </div>
        </dl>

        <div class="card-footer space-y-3">
            @if ($attendance->is_manual || $attendance->is_edited)
                <div class="alert-warning">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                    <span class="text-xs">This record was {{ $attendance->is_manual ? 'manually added' : '' }}{{ $attendance->is_manual && $attendance->is_edited ? ' and ' : '' }}{{ $attendance->is_edited ? 'edited by an administrator' : '' }}.</span>
                </div>
            @endif
            @if (auth()->user()->isAdmin())
                <a href="{{ route('admin.dtr.edit', $attendance) }}" class="btn-primary btn-block">Correct DTR</a>
            @endif
        </div>
    </div>

    <div class="card overflow-hidden lg:col-span-2">
        <div class="card-header">
            <h2 class="card-title">DTR Change History</h2>
            <span class="chip">{{ $attendance->edits->count() }} correction(s)</span>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Field</th><th>Original</th><th>New</th><th>Reason</th><th>Modified By</th><th>When</th></tr></thead>
                <tbody>
                    @forelse ($attendance->edits as $edit)
                        @php($changes = $edit->field_changes ?: [])
                        @if (count($changes))
                            @foreach ($changes as $change)
                                <tr>
                                    <td class="font-medium text-ink">{{ str_replace('_', ' ', ucfirst($change['attendance_type'] ?? $change['field'] ?? '—')) }}</td>
                                    <td class="tabular-nums text-muted">{{ isset($change['original']) ? \Carbon\Carbon::parse($change['original'])->format('h:i A') : '—' }}</td>
                                    <td class="font-semibold text-brand-700 tabular-nums">{{ isset($change['new']) ? \Carbon\Carbon::parse($change['new'])->format('h:i A') : '—' }}</td>
                                    <td>{{ $loop->first ? $edit->reason : '' }}</td>
                                    <td class="font-medium text-ink">{{ $loop->first ? $edit->modifier?->name : '' }}</td>
                                    <td class="whitespace-nowrap text-muted">{{ $loop->first ? $edit->modified_at?->format('M d, Y g:i A') : '' }}</td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td>Legacy</td>
                                <td class="tabular-nums text-muted">{{ $edit->original_time_in?->format('h:i A') ?? '—' }} / {{ $edit->original_time_out?->format('h:i A') ?? '—' }}</td>
                                <td class="font-semibold text-brand-700 tabular-nums">{{ $edit->new_time_in?->format('h:i A') ?? '—' }} / {{ $edit->new_time_out?->format('h:i A') ?? '—' }}</td>
                                <td>{{ $edit->reason }}</td>
                                <td class="font-medium text-ink">{{ $edit->modifier?->name }}</td>
                                <td class="whitespace-nowrap text-muted">{{ $edit->modified_at?->format('M d, Y g:i A') }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="6" class="p-0"><x-empty-state title="No edits" message="This DTR has not been modified." icon="shield" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
