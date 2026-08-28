@extends('layouts.app')

@section('title', 'My Dashboard')
@section('page-title', 'Today\'s Attendance')
@section('page-subtitle', 'Clock in and out using Philippine Standard Time')

@section('content')
<div class="space-y-6" x-data="clockPanel({
    timeInUrl: @js(route('attendance.time-in')),
    timeOutUrl: @js(route('attendance.time-out')),
    canTimeIn: @js($canTimeIn),
    canTimeOut: @js($canTimeOut),
})">
    @if ($todayHoliday)
        <div class="alert-success">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.58 9.11c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            <span>
                <strong class="font-bold">{{ $todayHoliday->name }}</strong> — {{ $todayHoliday->effectLabel() }}.
                You will not be marked absent today.
            </span>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="card card-accent-brand overflow-hidden lg:col-span-2">
            <div class="flex flex-wrap items-start justify-between gap-4 p-6">
                <div>
                    <div class="stat-label">Today's Attendance</div>
                    <div class="mt-1 text-2xl font-extrabold tracking-tight text-ink" x-text="dateLabel">{{ now()->toFormattedDateString() }}</div>
                    <div class="mt-3 text-xs font-semibold uppercase tracking-wide text-muted">Current time (PST)</div>
                    <div class="text-4xl font-extrabold text-brand-700 tabular-nums" x-text="timeLabel">{{ now()->format('h:i:s A') }}</div>
                </div>
                <x-status-badge :status="$today?->status" />
            </div>

            <div class="grid gap-4 px-6 sm:grid-cols-2">
                <div class="rounded-2xl border border-brand-200 bg-brand-50/50 p-5">
                    <div class="flex items-center gap-2">
                        <div class="stat-icon-brand h-8 w-8">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </div>
                        <div class="text-xs font-bold uppercase tracking-wide text-brand-800">Time In</div>
                    </div>
                    <div class="mt-3 text-2xl font-extrabold text-ink tabular-nums" id="time-in-label">{{ $today?->time_in?->format('h:i A') ?? '—' }}</div>
                    <form method="POST" action="{{ route('attendance.time-in') }}" class="mt-4" @submit.prevent="confirmIn">
                        @csrf
                        <button id="btn-in" type="submit" class="btn-primary btn-block" :disabled="!canTimeIn" @disabled(! $canTimeIn)>Time In</button>
                    </form>
                </div>
                <div class="rounded-2xl border border-info-200 bg-info-50/50 p-5">
                    <div class="flex items-center gap-2">
                        <div class="stat-icon-info h-8 w-8">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                        </div>
                        <div class="text-xs font-bold uppercase tracking-wide text-info-800">Time Out</div>
                    </div>
                    <div class="mt-3 text-2xl font-extrabold text-ink tabular-nums" id="time-out-label">{{ $today?->time_out?->format('h:i A') ?? '—' }}</div>
                    <form method="POST" action="{{ route('attendance.time-out') }}" class="mt-4" @submit.prevent="confirmOut">
                        @csrf
                        <button id="btn-out" type="submit" class="btn-secondary btn-block" :disabled="!canTimeOut" @disabled(! $canTimeOut)>Time Out</button>
                    </form>
                </div>
            </div>

            <div class="p-6">
                <div class="alert-info">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span class="text-xs">The server timestamp is used, not your device clock. You can Time In once per workday and Time Out only after Time In.</span>
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card overflow-hidden">
                <div class="card-header">
                    <h3 class="card-title">Today's Totals</h3>
                </div>
                <dl class="divide-y divide-line px-5 text-sm">
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-muted">Total hours</dt>
                        <dd class="font-bold text-ink tabular-nums">{{ $today?->totalHoursLabel() ?? '0:00' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-muted">Late</dt>
                        <dd class="tabular-nums {{ ($today?->late_minutes ?? 0) > 0 ? 'font-bold text-warn-700' : 'text-ink' }}">{{ $today?->late_minutes ?? 0 }} min</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-muted">Undertime</dt>
                        <dd class="tabular-nums {{ ($today?->undertime_minutes ?? 0) > 0 ? 'font-bold text-warn-700' : 'text-ink' }}">{{ $today?->undertime_minutes ?? 0 }} min</dd>
                    </div>
                    <div class="flex justify-between gap-4 py-2.5">
                        <dt class="text-muted">Overtime</dt>
                        <dd class="tabular-nums {{ ($today?->overtime_minutes ?? 0) > 0 ? 'font-bold text-gold-700' : 'text-ink' }}">{{ $today?->overtimeHoursLabel() ?? '0:00' }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 py-2.5">
                        <dt class="text-muted">Status</dt>
                        <dd><x-status-badge :status="$today?->status" /></dd>
                    </div>
                </dl>
            </div>

            <div class="card card-accent-gold p-5">
                <div class="flex items-center gap-3">
                    <div class="brand-mark h-10 w-10 text-sm">{{ strtoupper(substr($employee->fullName(), 0, 1)) }}</div>
                    <div class="min-w-0">
                        <div class="truncate font-bold text-ink">{{ $employee->fullName() }}</div>
                        <div class="truncate text-sm text-muted">{{ $employee->department?->name }} · {{ $employee->position }}</div>
                    </div>
                </div>
                <div class="mt-4 flex items-center gap-2 border-t border-line pt-3 text-xs">
                    <span class="text-muted">Schedule</span>
                    <span class="badge-gold">{{ $employee->schedule()->name }}</span>
                </div>
            </div>

            <x-calendar.upcoming
                :events="$upcomingEvents"
                :calendar-url="route('employee.calendar')"
                title="Upcoming for You"
                empty-message="You have no upcoming holidays, meetings, or announcements." />
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">This month's DTR</h2>
            <a class="link text-xs" href="{{ route('employee.dtr') }}">Full monthly DTR</a>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                        <th class="text-right">Hours</th>
                        <th class="text-right">Late</th>
                        <th class="text-right">UT</th>
                        <th class="text-right">OT</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($monthly as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink">
                                {{ optional($row->attendance_date)->format('M d, Y') }}
                                <x-holiday-tag :date="optional($row->attendance_date)->toDateString()" :employee="$employee" compact />
                            </td>
                            <td class="font-medium text-brand-700 tabular-nums">{{ $row->time_in?->format('h:i A') ?? '—' }}</td>
                            <td class="font-medium text-info-700 tabular-nums">{{ $row->time_out?->format('h:i A') ?? '—' }}</td>
                            <td class="text-right font-semibold text-ink tabular-nums">{{ $row->totalHoursLabel() }}</td>
                            <td class="text-right tabular-nums {{ $row->late_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->late_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->undertime_minutes > 0 ? 'font-bold text-warn-700' : 'text-muted' }}">{{ $row->undertime_minutes }}</td>
                            <td class="text-right tabular-nums {{ $row->overtime_minutes > 0 ? 'font-bold text-gold-700' : 'text-muted' }}">{{ $row->overtime_minutes }}</td>
                            <td><x-status-badge :status="$row->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state title="No records yet this month" message="Your attendance will appear here after your first Time In." icon="clock" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div x-show="dialog" x-cloak class="modal-backdrop flex items-center justify-center p-4" @click.self="dialog = false">
        <div class="modal-panel max-w-sm">
            <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-brand-100 text-brand-700">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 class="modal-title" x-text="dialogTitle"></h3>
            <p class="mt-2 text-sm text-muted" x-text="dialogBody"></p>
            <div class="modal-actions">
                <button type="button" class="btn-outline flex-1" @click="dialog = false">Cancel</button>
                <button type="button" class="btn-primary flex-1" @click="submitPending">Confirm</button>
            </div>
        </div>
    </div>
</div>
@endsection
