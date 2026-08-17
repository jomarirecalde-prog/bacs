@extends('layouts.app')

@section('title', 'Work Schedules')
@section('page-title', 'Work Schedules')
@section('page-subtitle', 'Configurable attendance rules')

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    @if (auth()->user()->isAdmin())
    <div class="card p-6">
        <h2 class="font-bold mb-4">Add schedule</h2>
        <form method="POST" action="{{ route('admin.schedules.store') }}" class="space-y-3">
            @csrf
            <div><label class="label">Name</label><input class="input" name="name" required></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label">Work start</label><input class="input" type="time" name="start_time" value="08:00" required></div>
                <div><label class="label">Work end</label><input class="input" type="time" name="end_time" value="17:00" required></div>
            </div>
            <div><label class="label">Grace period (minutes)</label><input class="input" type="number" name="grace_period_minutes" value="10" min="0" required></div>
            <div class="grid grid-cols-2 gap-2">
                <div><label class="label">Lunch start</label><input class="input" type="time" name="break_start" value="12:00"></div>
                <div><label class="label">Lunch end</label><input class="input" type="time" name="break_end" value="13:00"></div>
            </div>
            <div><label class="label">Required minutes</label><input class="input" type="number" name="required_minutes" value="480" required></div>
            <div>
                <label class="label">Work days</label>
                <div class="grid grid-cols-2 gap-1 text-sm">
                    @foreach ([1=>'Mon',2=>'Tue',3=>'Wed',4=>'Thu',5=>'Fri',6=>'Sat',7=>'Sun'] as $n => $d)
                        <label class="flex items-center gap-2"><input type="checkbox" name="work_days[]" value="{{ $n }}" @checked($n <= 5)> {{ $d }}</label>
                    @endforeach
                </div>
            </div>
            <input type="hidden" name="status" value="active">
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_default" value="1"> Default schedule</label>
            <button class="btn-primary">Save schedule</button>
        </form>
    </div>
    @endif
    <div class="{{ auth()->user()->isAdmin() ? 'lg:col-span-2' : 'lg:col-span-3' }} space-y-4">
        @foreach ($schedules as $schedule)
            <div class="card p-5">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="font-bold">{{ $schedule->name }} @if($schedule->is_default)<span class="text-xs text-brand-700">Default</span>@endif</h3>
                        <p class="text-sm text-slate-500">
                            {{ \Carbon\Carbon::parse($schedule->start_time)->format('g:i A') }} –
                            {{ \Carbon\Carbon::parse($schedule->end_time)->format('g:i A') }}
                            · Grace {{ $schedule->grace_period_minutes }} min
                            · Lunch {{ $schedule->break_start ? \Carbon\Carbon::parse($schedule->break_start)->format('g:i A').'–'.\Carbon\Carbon::parse($schedule->break_end)->format('g:i A') : 'none' }}
                        </p>
                        <p class="text-xs text-slate-500 mt-1">{{ $schedule->employees_count }} employees assigned</p>
                    </div>
                    <span class="text-xs uppercase font-semibold">{{ $schedule->status?->label() }}</span>
                </div>
                @if (auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('admin.schedules.update', $schedule) }}" class="mt-4 grid gap-2 md:grid-cols-4">
                        @csrf @method('PUT')
                        <input class="input" name="name" value="{{ $schedule->name }}">
                        <input class="input" type="time" name="start_time" value="{{ substr($schedule->start_time,0,5) }}">
                        <input class="input" type="time" name="end_time" value="{{ substr($schedule->end_time,0,5) }}">
                        <input class="input" type="number" name="grace_period_minutes" value="{{ $schedule->grace_period_minutes }}">
                        <input class="input" type="time" name="break_start" value="{{ $schedule->break_start ? substr($schedule->break_start,0,5) : '' }}">
                        <input class="input" type="time" name="break_end" value="{{ $schedule->break_end ? substr($schedule->break_end,0,5) : '' }}">
                        <input class="input" type="number" name="required_minutes" value="{{ $schedule->required_minutes }}">
                        <select class="input" name="status">
                            <option value="active" @selected($schedule->status?->value === 'active')>Active</option>
                            <option value="inactive" @selected($schedule->status?->value === 'inactive')>Inactive</option>
                        </select>
                        @foreach ($schedule->workDays() as $day)
                            <input type="hidden" name="work_days[]" value="{{ $day }}">
                        @endforeach
                        <label class="flex items-center gap-2 text-sm md:col-span-3"><input type="checkbox" name="is_default" value="1" @checked($schedule->is_default)> Default</label>
                        <button class="btn-secondary">Update</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>
</div>
@endsection
