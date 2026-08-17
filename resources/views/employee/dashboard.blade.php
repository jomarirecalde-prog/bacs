@extends('layouts.app')

@section('title', 'My Dashboard')
@section('page-title', 'Today\'s Attendance')
@section('page-subtitle', 'Clock in and out using Philippine Standard Time')

@section('content')
<div class="space-y-6" x-data="clockPanel()">
<div class="grid gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2 card p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">Today's Attendance</div>
                <div class="mt-1 text-2xl font-extrabold" x-text="dateLabel">{{ now()->toFormattedDateString() }}</div>
                <div class="text-sm text-slate-500">Current time (PST)</div>
                <div class="text-4xl font-extrabold tabular-nums text-brand-800" x-text="timeLabel">{{ now()->format('h:i:s A') }}</div>
            </div>
            <x-status-badge :status="$today?->status" />
        </div>

        <div class="mt-8 grid gap-4 sm:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 p-5">
                <div class="text-xs font-semibold uppercase text-slate-500">Time In</div>
                <div class="mt-2 text-2xl font-bold" id="time-in-label">{{ $today?->time_in?->format('h:i A') ?? '—' }}</div>
                <form method="POST" action="{{ route('attendance.time-in') }}" class="mt-4" @submit.prevent="confirmIn">
                    @csrf
                    <button id="btn-in" class="btn-primary w-full" @disabled(! $canTimeIn)>Time In</button>
                </form>
            </div>
            <div class="rounded-2xl border border-slate-200 p-5">
                <div class="text-xs font-semibold uppercase text-slate-500">Time Out</div>
                <div class="mt-2 text-2xl font-bold" id="time-out-label">{{ $today?->time_out?->format('h:i A') ?? '—' }}</div>
                <form method="POST" action="{{ route('attendance.time-out') }}" class="mt-4" @submit.prevent="confirmOut">
                    @csrf
                    <button id="btn-out" class="btn-primary w-full" @disabled(! $canTimeOut)>Time Out</button>
                </form>
            </div>
        </div>
        <p class="mt-4 text-xs text-slate-500">The server timestamp is used, not your device clock. You can Time In once per workday and Time Out only after Time In.</p>
    </div>

    <div class="space-y-4">
        <div class="card p-5 space-y-3 text-sm">
            <div class="flex justify-between"><span class="text-slate-500">Total hours</span><span class="font-semibold">{{ $today?->totalHoursLabel() ?? '0:00' }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Late</span><span>{{ $today?->late_minutes ?? 0 }} min</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Undertime</span><span>{{ $today?->undertime_minutes ?? 0 }} min</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Overtime</span><span>{{ $today?->overtimeHoursLabel() ?? '0:00' }}</span></div>
            <div class="flex justify-between items-center"><span class="text-slate-500">Status</span><x-status-badge :status="$today?->status" /></div>
        </div>
        <div class="card p-5">
            <div class="font-bold">{{ $employee->fullName() }}</div>
            <div class="text-sm text-slate-500">{{ $employee->department?->name }} · {{ $employee->position }}</div>
            <div class="text-xs text-slate-500 mt-2">Schedule: {{ $employee->schedule()->name }}</div>
        </div>
    </div>
</div>

<div class="card overflow-hidden mt-6">
    <div class="px-5 py-4 border-b font-bold">This month's DTR</div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Late</th><th>UT</th><th>OT</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($monthly as $row)
                    <tr>
                        <td>{{ optional($row->attendance_date)->format('M d, Y') }}</td>
                        <td>{{ $row->time_in?->format('h:i A') ?? '—' }}</td>
                        <td>{{ $row->time_out?->format('h:i A') ?? '—' }}</td>
                        <td>{{ $row->totalHoursLabel() }}</td>
                        <td>{{ $row->late_minutes }}</td>
                        <td>{{ $row->undertime_minutes }}</td>
                        <td>{{ $row->overtime_minutes }}</td>
                        <td><x-status-badge :status="$row->status" /></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<div x-show="dialog" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
    <div class="card max-w-sm p-6">
        <h3 class="font-bold" x-text="dialogTitle"></h3>
        <p class="mt-2 text-sm text-slate-500" x-text="dialogBody"></p>
        <div class="mt-5 flex gap-2">
            <button class="btn-secondary flex-1" @click="dialog = false">Cancel</button>
            <button class="btn-primary flex-1" @click="submitPending">Confirm</button>
        </div>
    </div>
</div>
</div>

<script>
function clockPanel() {
    return {
        dateLabel: '',
        timeLabel: '',
        offset: 0,
        dialog: false,
        dialogTitle: '',
        dialogBody: '',
        pendingUrl: null,
        async init() {
            try {
                const res = await fetch('{{ route('server-time') }}', { headers: { Accept: 'application/json' } });
                const data = await res.json();
                this.offset = data.timestamp - Date.now();
            } catch { this.offset = 0; }
            this.tick();
            setInterval(() => this.tick(), 1000);
        },
        tick() {
            const now = new Date(Date.now() + this.offset);
            const opts = { timeZone: 'Asia/Manila' };
            this.dateLabel = now.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', ...opts });
            this.timeLabel = now.toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true, ...opts });
        },
        confirmIn() {
            this.dialogTitle = 'Confirm Time In';
            this.dialogBody = 'Record your Time In using the server timestamp?';
            this.pendingUrl = '{{ route('attendance.time-in') }}';
            this.dialog = true;
        },
        confirmOut() {
            this.dialogTitle = 'Confirm Time Out';
            this.dialogBody = 'Record your Time Out using the server timestamp?';
            this.pendingUrl = '{{ route('attendance.time-out') }}';
            this.dialog = true;
        },
        async submitPending() {
            this.dialog = false;
            const token = document.querySelector('meta[name="csrf-token"]').content;
            try {
                const res = await fetch(this.pendingUrl, {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await res.json();
                if (!res.ok) {
                    window.dtrToast(data.message || Object.values(data.errors || { e: ['Unable to save'] })[0][0], 'error');
                    return;
                }
                window.dtrToast(data.message, 'success');
                setTimeout(() => window.location.reload(), 700);
            } catch {
                window.dtrToast('Unable to record attendance.', 'error');
            }
        }
    }
}
</script>
@endsection
