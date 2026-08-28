@extends('layouts.app')

@section('title', 'Work Schedules')
@section('page-title', 'Work Schedules')
@section('page-subtitle', 'Configurable attendance rules')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    @if (auth()->user()->isAdmin())
        <div class="card card-accent-brand h-fit overflow-hidden">
            <div class="card-header">
                <h2 class="card-title">Add schedule</h2>
            </div>
            <form method="POST" action="{{ route('admin.schedules.store') }}" class="space-y-4 p-5">
                @csrf
                <div>
                    <label class="label" for="sched-name">Name</label>
                    <input id="sched-name" class="input" name="name" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="sched-start">Work start</label>
                        <input id="sched-start" class="input" type="time" name="start_time" value="08:00" required>
                    </div>
                    <div>
                        <label class="label" for="sched-end">Work end</label>
                        <input id="sched-end" class="input" type="time" name="end_time" value="17:00" required>
                    </div>
                </div>
                <div>
                    <label class="label" for="sched-grace">Grace period (minutes)</label>
                    <input id="sched-grace" class="input" type="number" name="grace_period_minutes" value="10" min="0" required>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label" for="sched-break-start">Lunch start</label>
                        <input id="sched-break-start" class="input" type="time" name="break_start" value="12:00">
                    </div>
                    <div>
                        <label class="label" for="sched-break-end">Lunch end</label>
                        <input id="sched-break-end" class="input" type="time" name="break_end" value="13:00">
                    </div>
                </div>
                <div>
                    <label class="label" for="sched-required">Required minutes</label>
                    <input id="sched-required" class="input" type="number" name="required_minutes" value="480" required>
                </div>
                <div>
                    <span class="label">Work days</span>
                    <div class="grid grid-cols-2 gap-1.5 text-sm">
                        @foreach ([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $n => $d)
                            <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-brand-50">
                                <input type="checkbox" name="work_days[]" value="{{ $n }}" class="checkbox" @checked($n <= 5)>
                                <span class="font-medium text-ink-soft">{{ $d }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <input type="hidden" name="status" value="active">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-gold-200 bg-gold-50 px-3 py-2.5 text-sm">
                    <input type="checkbox" name="is_default" value="1" class="checkbox">
                    <span class="font-semibold text-gold-800">Set as default schedule</span>
                </label>
                <button type="submit" class="btn-primary btn-block">Save schedule</button>
            </form>
        </div>
    @endif

    <div class="{{ auth()->user()->isAdmin() ? 'lg:col-span-2' : 'lg:col-span-3' }} space-y-4">
        @forelse ($schedules as $schedule)
            <div class="card {{ $schedule->is_default ? 'card-accent-gold' : '' }} overflow-hidden">
                <div class="card-header">
                    <div class="min-w-0">
                        <h3 class="flex flex-wrap items-center gap-2 text-sm font-bold text-ink">
                            {{ $schedule->name }}
                            @if ($schedule->is_default)
                                <span class="badge-featured">Default</span>
                            @endif
                        </h3>
                        <p class="mt-1 text-xs text-muted">
                            {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') }} –
                            {{ \Carbon\Carbon::parse($schedule->end_time)->format('g:i A') }}
                            · Grace {{ $schedule->grace_period_minutes }} min
                            · Lunch {{ $schedule->break_start ? \Carbon\Carbon::parse($schedule->break_start)->format('g:i A').'–'.\Carbon\Carbon::parse($schedule->break_end)->format('g:i A') : 'none' }}
                        </p>
                        <p class="mt-1 text-xs font-semibold text-info-700">{{ $schedule->employees_count }} employees assigned</p>
                    </div>
                    <span class="{{ $schedule->status?->value === 'active' ? 'badge-brand' : 'badge-neutral' }}">
                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                        {{ $schedule->status?->label() }}
                    </span>
                </div>

                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('admin.schedules.update', $schedule) }}" class="p-5">
                        @csrf @method('PUT')
                        <div class="grid gap-3 md:grid-cols-4">
                            <div>
                                <label class="label">Name</label>
                                <input class="input" name="name" value="{{ $schedule->name }}">
                            </div>
                            <div>
                                <label class="label">Work start</label>
                                <input class="input" type="time" name="start_time" value="{{ substr($schedule->start_time,0,5) }}">
                            </div>
                            <div>
                                <label class="label">Work end</label>
                                <input class="input" type="time" name="end_time" value="{{ substr($schedule->end_time,0,5) }}">
                            </div>
                            <div>
                                <label class="label">Grace (min)</label>
                                <input class="input" type="number" name="grace_period_minutes" value="{{ $schedule->grace_period_minutes }}">
                            </div>
                            <div>
                                <label class="label">Lunch start</label>
                                <input class="input" type="time" name="break_start" value="{{ $schedule->break_start ? substr($schedule->break_start,0,5) : '' }}">
                            </div>
                            <div>
                                <label class="label">Lunch end</label>
                                <input class="input" type="time" name="break_end" value="{{ $schedule->break_end ? substr($schedule->break_end,0,5) : '' }}">
                            </div>
                            <div>
                                <label class="label">Required min</label>
                                <input class="input" type="number" name="required_minutes" value="{{ $schedule->required_minutes }}">
                            </div>
                            <div>
                                <label class="label">Status</label>
                                <select class="select" name="status">
                                    <option value="active" @selected($schedule->status?->value === 'active')>Active</option>
                                    <option value="inactive" @selected($schedule->status?->value === 'inactive')>Inactive</option>
                                </select>
                            </div>
                        </div>
                        @foreach ($schedule->workDays() as $day)
                            <input type="hidden" name="work_days[]" value="{{ $day }}">
                        @endforeach
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <input type="checkbox" name="is_default" value="1" class="checkbox" @checked($schedule->is_default)>
                                <span class="font-semibold text-ink-soft">Default schedule</span>
                            </label>
                            <button type="submit" class="btn-secondary btn-sm">Update schedule</button>
                        </div>
                    </form>
                @endif
            </div>
        @empty
            <div class="card">
                <x-empty-state title="No work schedules" message="Add a schedule to define work hours, grace periods, and required minutes." icon="calendar" />
            </div>
        @endforelse
    </div>
</div>
@endsection
