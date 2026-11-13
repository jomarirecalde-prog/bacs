@extends('layouts.app')

@section('title', 'My Dashboard')
@section('page-title', 'Today\'s Attendance')
@section('page-subtitle', 'Clock in and out using Philippine Standard Time')

@php
    use App\Enums\AttendancePunchType;
    use App\Support\ManilaTime;

    $punchCards = [
        [
            'key' => AttendancePunchType::AmTimeIn->value,
            'label' => 'AM Time In',
            'value' => $today?->am_time_in,
            'icon' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v4',
        ],
        [
            'key' => AttendancePunchType::AmTimeOut->value,
            'label' => 'AM Time Out',
            'value' => $today?->am_time_out,
            'icon' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v4',
        ],
        [
            'key' => AttendancePunchType::PmTimeIn->value,
            'label' => 'PM Time In',
            'value' => $today?->pm_time_in,
            'icon' => 'M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v4',
        ],
        [
            'key' => AttendancePunchType::PmTimeOut->value,
            'label' => 'PM Time Out',
            'value' => $today?->pm_time_out,
            'icon' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v4',
        ],
        [
            'key' => AttendancePunchType::Overtime->value,
            'label' => 'Overtime',
            'value' => $today?->overtime_in,
            'icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
    ];

    $monthLabel = ManilaTime::parse(sprintf('%04d-%02d-01', $year, $month))->format('F Y');
    $lateMinutes = (int) ($today?->late_minutes ?? 0);
    $undertimeMinutes = (int) ($today?->undertime_minutes ?? 0);
    $overtimeMinutes = (int) ($today?->overtime_minutes ?? 0);
@endphp

@section('content')
<div class="w-full min-w-0 space-y-3 sm:space-y-4"
     x-data="clockPanel({
        timeInUrl: @js(route('attendance.time-in')),
        timeOutUrl: @js(route('attendance.time-out')),
        canTimeIn: @js($canRecord),
        canTimeOut: @js($canRecord),
        nextActionLabel: @js($nextAction?->label()),
    })">

    @if ($pendingCorrection)
        <div class="alert-warning shadow-soft" role="status">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warn-100 text-warn-800" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-sm font-bold text-warn-900">Pending attendance correction</span>
                <span class="mt-0.5 block text-sm text-warn-900/90">{{ $pendingCorrection }}</span>
                <a class="link mt-2 inline-flex min-h-11 items-center text-sm font-semibold" href="{{ route('employee.attendance-corrections.index') }}">View correction</a>
            </span>
        </div>
    @endif

    @if ($todayHoliday)
        <div class="alert-success shadow-soft" role="status">
            <span class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-100 text-brand-700" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.58 9.11c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            </span>
            <span class="min-w-0 flex-1 text-sm">
                <strong class="font-bold text-brand-900">{{ $todayHoliday->name }}</strong>
                <span class="text-brand-900/90"> — {{ $todayHoliday->effectLabel() }}. You will not be marked absent today.</span>
            </span>
        </div>
    @endif

    <div class="flex w-full min-w-0 flex-col gap-4 lg:flex-row lg:items-start lg:gap-5">
        <div class="w-full min-w-0 flex-1 space-y-4">
            <section class="card card-accent-brand w-full min-w-0 overflow-hidden shadow-soft" aria-labelledby="attendance-heading">
                <div class="flex flex-col gap-2 border-b border-line bg-brand-50/30 px-3 py-2.5 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:gap-x-4 sm:gap-y-2 sm:px-4">
                    <p class="min-w-0 text-sm font-semibold text-muted" x-text="dateLabel" aria-live="polite">{{ now()->timezone('Asia/Manila')->format('l, F j, Y') }}</p>
                    <div class="flex flex-wrap items-center gap-2 sm:gap-3">
                        <h2 id="attendance-heading" class="sr-only">Today's Attendance</h2>
                        <span class="text-xl font-extrabold tabular-nums text-brand-700 sm:text-2xl" x-text="timeLabel" aria-live="polite">{{ now()->timezone('Asia/Manila')->format('h:i:s A') }}</span>
                        <span class="badge-brand text-[10px]">PST</span>
                        <x-status-badge :status="$today?->status" />
                    </div>
                    @if ($nextAction)
                        <div class="flex w-full min-w-0 items-center gap-2 rounded-xl border border-gold-200 bg-gold-50/90 px-2.5 py-1.5 sm:ml-auto sm:w-auto" role="status">
                            <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-gold-800">Next</span>
                            <span class="min-w-0 text-sm font-extrabold text-gold-900" id="next-action-label">{{ $nextAction->label() }}</span>
                        </div>
                    @endif
                </div>

                <div class="w-full min-w-0 p-3 sm:p-4">
                    <ul class="grid w-full min-w-0 grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3 lg:grid-cols-5" role="list">
                        @foreach ($punchCards as $punch)
                            @php
                                $recorded = $punch['value'] !== null;
                                $isNext = $nextAction && $nextAction->value === $punch['key'];
                                $state = $recorded ? 'recorded' : ($isNext ? 'pending' : 'empty');
                                $surface = match ($state) {
                                    'recorded' => 'border-brand-200 bg-brand-50/70',
                                    'pending' => 'border-gold-300 bg-gold-50/80 ring-1 ring-gold-200/70',
                                    default => 'border-line bg-canvas/60',
                                };
                                $statusLabel = match ($state) {
                                    'recorded' => 'Recorded',
                                    'pending' => 'Up next',
                                    default => 'Not recorded',
                                };
                            @endphp
                            <li class="min-w-0">
                                <div class="flex h-full min-w-0 flex-col gap-1.5 rounded-xl border p-2.5 {{ $surface }}">
                                    <div class="flex items-start justify-between gap-1">
                                        <span class="text-[10px] font-bold uppercase leading-tight text-muted">{{ $punch['label'] }}</span>
                                        <span @class([
                                            'h-2 w-2 shrink-0 rounded-full',
                                            'bg-brand-500' => $state === 'recorded',
                                            'bg-gold-500' => $state === 'pending',
                                            'bg-line-strong' => $state === 'empty',
                                        ]) title="{{ $statusLabel }}" aria-label="{{ $statusLabel }}"></span>
                                    </div>
                                    <p class="text-base font-extrabold leading-tight text-ink tabular-nums punch-label sm:text-lg" data-punch="{{ $punch['key'] }}">
                                        {{ $punch['value']?->format('h:i A') ?? '—' }}
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-2.5 text-[11px] leading-snug text-muted sm:mt-3">
                        Server timestamp (PST), not device time · AM In → AM Out → PM In → PM Out → OT
                    </p>
                </div>
            </section>

            @include('employee.partials.dashboard-totals', [
                'today' => $today,
                'lateMinutes' => $lateMinutes,
                'undertimeMinutes' => $undertimeMinutes,
                'overtimeMinutes' => $overtimeMinutes,
            ])

            <a href="{{ route('employee.overtime-attachment.index') }}" class="card flex items-center justify-between gap-3 p-4 shadow-soft transition hover:border-brand-200">
                <div>
                    <p class="text-xs font-bold uppercase tracking-wide text-muted">Overtime</p>
                    <p class="mt-0.5 text-sm font-bold text-ink">OT Attachment Reports</p>
                    <p class="mt-0.5 text-xs text-muted">Document activities for DTR overtime; payroll reviews on the period OT page.</p>
                </div>
                <span class="btn-outline btn-sm shrink-0">Open</span>
            </a>
        </div>

        <aside class="w-full min-w-0 shrink-0 space-y-4 lg:w-72 xl:w-80">
            <section class="card w-full min-w-0 overflow-hidden shadow-soft" aria-labelledby="profile-heading">
                <div class="flex items-center gap-3 p-3">
                    @if ($employee->photo)
                        <img src="{{ $employee->photoUrl() }}" alt="" loading="lazy" decoding="async" class="h-11 w-11 shrink-0 rounded-xl object-cover ring-2 ring-brand-100">
                    @else
                        <div class="brand-mark flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-sm" aria-hidden="true">
                            {{ strtoupper(mb_substr($employee->fullName(), 0, 2)) }}
                        </div>
                    @endif
                    <div class="min-w-0 flex-1">
                        <h3 id="profile-heading" class="truncate text-sm font-bold text-ink">{{ $employee->fullName() }}</h3>
                        <p class="truncate text-xs text-muted">{{ $employee->department?->name ?? '—' }} · {{ $employee->position ?? '—' }}</p>
                        <span class="badge-gold mt-1 inline-block text-[10px]">{{ $employee->schedule()->name }}</span>
                    </div>
                </div>
            </section>

            <div class="w-full min-w-0 [&_.card]:w-full">
                <x-calendar.upcoming
                    :events="$upcomingEvents"
                    :calendar-url="route('employee.calendar')"
                    title="Upcoming for You"
                    empty-message="You have no upcoming holidays, meetings, or announcements." />
            </div>
        </aside>
    </div>

    <section class="card overflow-hidden shadow-soft" aria-labelledby="dtr-heading">
        <div class="flex items-center justify-between gap-2 border-b border-line px-3 py-2 sm:px-4">
            <div>
                <h2 id="dtr-heading" class="text-sm font-extrabold text-ink">This month's DTR</h2>
                <p class="text-[11px] text-muted">{{ $monthLabel }}</p>
            </div>
            <a class="link shrink-0 text-xs font-semibold" href="{{ route('employee.dtr') }}">Full DTR</a>
        </div>

        <div class="table-wrap hidden md:block">
            <table class="data-table">
                <thead>
                    <tr>
                        <th scope="col">Date</th>
                        <th scope="col">Time In</th>
                        <th scope="col">Time Out</th>
                        <th scope="col" class="text-right">Hours</th>
                        <th scope="col" class="text-right">Late</th>
                        <th scope="col" class="text-right">UT</th>
                        <th scope="col" class="text-right">OT</th>
                        <th scope="col">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($monthly as $row)
                        <tr>
                            <td class="whitespace-nowrap font-medium text-ink">
                                {{ optional($row->attendance_date)->format('M d, Y') }}
                                <x-holiday-tag :date="optional($row->attendance_date)->toDateString()" :employee="$employee" compact />
                            </td>
                            <td class="font-semibold text-brand-700 tabular-nums">{{ $row->time_in?->format('h:i A') ?? '—' }}</td>
                            <td class="font-semibold text-info-700 tabular-nums">{{ $row->time_out?->format('h:i A') ?? '—' }}</td>
                            <td class="text-right font-semibold text-ink tabular-nums">{{ $row->totalHoursLabel() }}</td>
                            <td @class(['text-right tabular-nums', 'font-bold text-warn-700' => $row->late_minutes > 0, 'text-muted' => $row->late_minutes <= 0])>{{ $row->late_minutes }}</td>
                            <td @class(['text-right tabular-nums', 'font-bold text-warn-700' => $row->undertime_minutes > 0, 'text-muted' => $row->undertime_minutes <= 0])>{{ $row->undertime_minutes }}</td>
                            <td @class(['text-right tabular-nums', 'font-bold text-gold-700' => $row->overtime_minutes > 0, 'text-muted' => $row->overtime_minutes <= 0])>{{ $row->overtime_minutes }}</td>
                            <td><x-status-badge :status="$row->status" /></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="p-0"><x-empty-state title="No records yet this month" message="Your attendance will appear here after your first Time In." icon="clock" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="space-y-3 p-4 md:hidden">
            @forelse ($monthly as $row)
                <article class="rounded-2xl border border-line bg-surface p-4 shadow-soft">
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-sm font-bold text-ink">{{ optional($row->attendance_date)->format('M d, Y') }}</h3>
                        <x-status-badge :status="$row->status" />
                    </div>
                    <x-holiday-tag :date="optional($row->attendance_date)->toDateString()" :employee="$employee" compact class="mt-1" />
                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-xl bg-canvas/80 px-2 py-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-muted">In</dt>
                            <dd class="mt-0.5 text-sm font-bold text-brand-700 tabular-nums">{{ $row->time_in?->format('h:i A') ?? '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-canvas/80 px-2 py-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-muted">Out</dt>
                            <dd class="mt-0.5 text-sm font-bold text-info-700 tabular-nums">{{ $row->time_out?->format('h:i A') ?? '—' }}</dd>
                        </div>
                        <div class="rounded-xl bg-brand-50/80 px-2 py-2">
                            <dt class="text-[10px] font-bold uppercase tracking-wide text-muted">Hours</dt>
                            <dd class="mt-0.5 text-sm font-bold text-ink tabular-nums">{{ $row->totalHoursLabel() }}</dd>
                        </div>
                    </dl>
                    <dl class="mt-2 grid grid-cols-3 gap-2 text-center text-xs">
                        <div>
                            <dt class="font-semibold text-muted">Late</dt>
                            <dd @class(['mt-0.5 tabular-nums font-bold', 'text-warn-700' => $row->late_minutes > 0, 'text-faint' => $row->late_minutes <= 0])>{{ $row->late_minutes }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-muted">UT</dt>
                            <dd @class(['mt-0.5 tabular-nums font-bold', 'text-warn-700' => $row->undertime_minutes > 0, 'text-faint' => $row->undertime_minutes <= 0])>{{ $row->undertime_minutes }}</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-muted">OT</dt>
                            <dd @class(['mt-0.5 tabular-nums font-bold', 'text-gold-700' => $row->overtime_minutes > 0, 'text-faint' => $row->overtime_minutes <= 0])>{{ $row->overtime_minutes }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <x-empty-state title="No records yet this month" message="Your attendance will appear here after your first Time In." icon="clock" />
            @endforelse
        </div>
    </section>

    <div x-show="dialog"
         x-cloak
         class="modal-backdrop"
         role="dialog"
         aria-modal="true"
         aria-labelledby="clock-dialog-title"
         @keydown.escape.window="dialog = false"
         @click.self="dialog && !submitting ? dialog = false : null">
        <div class="modal-panel max-w-md" @click.stop>
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-brand-100 text-brand-700" aria-hidden="true">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
            <h3 id="clock-dialog-title" class="modal-title" x-text="dialogTitle"></h3>
            <p class="mt-2 text-sm text-muted" x-text="dialogBody"></p>
            <div class="modal-actions">
                <button type="button" class="btn-outline flex-1" @click="dialog = false" x-bind:disabled="submitting">Cancel</button>
                <button type="button"
                        class="btn-primary flex-1"
                        @click="submitPending"
                        x-bind:disabled="submitting"
                        x-bind:aria-busy="submitting">
                    <span x-show="!submitting">Confirm</span>
                    <span x-show="submitting" x-cloak>Recording…</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
