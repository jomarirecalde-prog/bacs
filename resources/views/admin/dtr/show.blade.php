@extends('layouts.app')

@section('title', 'DTR Record')
@section('page-title', 'DTR Record')
@section('page-subtitle', $attendance->employee?->fullName().' · '.$attendance->attendance_date->toFormattedDateString())

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-6 space-y-3">
        <div class="flex justify-between"><span class="text-slate-500">Employee</span><span class="font-semibold">{{ $attendance->employee?->fullName() }}</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Department</span><span>{{ $attendance->employee?->department?->name }}</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Time In</span><span>{{ $attendance->time_in?->format('h:i A') ?? '—' }}</span></div>
        @if ($attendance->time_in_station_name)
            <div class="flex justify-between text-xs"><span class="text-slate-500">Time In Station</span><span class="text-right">{{ $attendance->time_in_station_name }}<br>{{ $attendance->time_in_station_location }}</span></div>
        @endif
        <div class="flex justify-between"><span class="text-slate-500">Time Out</span><span>{{ $attendance->time_out?->format('h:i A') ?? '—' }}</span></div>
        @if ($attendance->time_out_station_name)
            <div class="flex justify-between text-xs"><span class="text-slate-500">Time Out Station</span><span class="text-right">{{ $attendance->time_out_station_name }}<br>{{ $attendance->time_out_station_location }}</span></div>
        @endif
        <div class="flex justify-between"><span class="text-slate-500">Hours</span><span>{{ $attendance->totalHoursLabel() }}</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Late</span><span>{{ $attendance->late_minutes }} min</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Undertime</span><span>{{ $attendance->undertime_minutes }} min</span></div>
        <div class="flex justify-between"><span class="text-slate-500">Overtime</span><span>{{ $attendance->overtimeHoursLabel() }}</span></div>
        <div class="flex justify-between items-center"><span class="text-slate-500">Status</span><x-status-badge :status="$attendance->status" /></div>
        <div><span class="text-slate-500 text-sm">Remarks</span><p>{{ $attendance->remarks ?: '—' }}</p></div>
        @if ($attendance->is_manual || $attendance->is_edited)
            <p class="text-xs text-amber-700">This record was {{ $attendance->is_manual ? 'manually added' : '' }}{{ $attendance->is_manual && $attendance->is_edited ? ' and ' : '' }}{{ $attendance->is_edited ? 'edited by an administrator' : '' }}.</p>
        @endif
        @if (auth()->user()->isAdmin())
            <a href="{{ route('admin.dtr.edit', $attendance) }}" class="btn-primary w-full">Correct DTR</a>
        @endif
    </div>
    <div class="lg:col-span-2 card overflow-hidden">
        <div class="px-5 py-4 border-b font-bold">DTR Change History</div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Original In</th><th>Original Out</th><th>New In</th><th>New Out</th><th>Reason</th><th>Modified By</th><th>When</th></tr></thead>
                <tbody>
                    @forelse ($attendance->edits as $edit)
                        <tr>
                            <td>{{ $edit->original_time_in?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $edit->original_time_out?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $edit->new_time_in?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $edit->new_time_out?->format('h:i A') ?? '—' }}</td>
                            <td>{{ $edit->reason }}</td>
                            <td>{{ $edit->modifier?->name }}</td>
                            <td>{{ $edit->modified_at?->format('M d, Y g:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-empty-state title="No edits" message="This DTR has not been modified." /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
