@extends('layouts.app')

@section('title', 'Request DTR Correction')
@section('page-title', 'Request DTR Correction')
@section('page-subtitle', 'Correct a specific AM/PM entry')

@section('content')
<div
    class="max-w-3xl space-y-4"
    x-data="correctionForm({
        previewUrl: @js(route('employee.attendance-corrections.day-preview')),
        date: @js(old('attendance_date', $date)),
        punchType: @js(old('punch_type')),
        requestedTime: @js(old('requested_time')),
        preview: @js($dayPreview),
    })"
>
    <div class="alert-info">
        <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <span>Pick a date, then enter the correct time on the punch you need fixed. After approval, only that field is updated.</span>
    </div>

    <div class="card card-accent-brand overflow-hidden">
        <div class="card-header">
            <h2 class="card-title">Correction details</h2>
        </div>
        <form
            method="POST"
            action="{{ route('employee.attendance-corrections.store') }}"
            class="space-y-5 p-5"
            @submit="beforeSubmit"
        >
            @csrf
            <input type="hidden" name="punch_type" :value="punchType">
            <input type="hidden" name="requested_time" :value="requestedTime">

            <div>
                <label class="label" for="attendance_date">Attendance date</label>
                <input
                    id="attendance_date"
                    class="input @error('attendance_date') input-error @enderror"
                    type="date"
                    name="attendance_date"
                    x-model="date"
                    @change="refreshPreview()"
                    max="{{ now()->toDateString() }}"
                    required
                >
                @error('attendance_date')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="rounded-2xl border border-line bg-canvas/60 p-4 sm:p-5" x-show="preview" x-cloak>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-muted">Daily snapshot</p>
                        <p class="mt-1 text-base font-bold text-ink" x-text="preview?.date_label + ' · ' + preview?.day_name"></p>
                        <p class="mt-0.5 text-xs text-muted">
                            Schedule: <span x-text="preview?.schedule?.name"></span>
                            · <span x-text="preview?.schedule?.start"></span> – <span x-text="preview?.schedule?.end"></span>
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-if="preview?.day_status">
                            <span class="chip" x-text="preview.day_status"></span>
                        </template>
                        <template x-if="preview?.late_minutes > 0">
                            <span class="badge-warn" x-text="'Late ' + preview.late_minutes + ' min'"></span>
                        </template>
                        <template x-if="preview?.undertime_minutes > 0">
                            <span class="badge-warn" x-text="'Undertime ' + preview.undertime_minutes + ' min'"></span>
                        </template>
                        <template x-if="preview?.incomplete">
                            <span class="badge-warn">Incomplete day</span>
                        </template>
                    </div>
                </div>

                <ul class="mt-3 space-y-1 text-sm text-muted" x-show="preview?.highlights?.length">
                    <template x-for="(line, index) in preview?.highlights || []" :key="index">
                        <li class="flex gap-2">
                            <span class="mt-2 h-1 w-1 shrink-0 rounded-full bg-brand-500"></span>
                            <span x-text="line"></span>
                        </li>
                    </template>
                </ul>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-muted">Edit the punch to correct</p>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    <template x-for="punch in preview?.punches || []" :key="punch.type">
                        <div
                            class="rounded-xl border p-4 transition"
                            :class="punchType === punch.type
                                ? 'border-brand-400 bg-brand-50/80 ring-2 ring-brand-200'
                                : (punch.pending ? 'border-line bg-surface/60 opacity-75' : 'border-line bg-surface')"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <span class="text-sm font-semibold text-ink" x-text="punch.label"></span>
                                <span
                                    class="text-base font-bold tabular-nums"
                                    :class="punch.missing ? 'text-warn-700' : 'text-brand-700'"
                                    x-text="punch.time || '—'"
                                ></span>
                            </div>
                            <p class="mt-0.5 text-xs text-muted" x-text="punch.missing ? 'Not recorded on DTR' : 'Currently recorded'"></p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <template x-for="tag in punch.tags || []" :key="tag.key">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                                        :class="{
                                            'bg-brand-100 text-brand-800': tag.tone === 'ok',
                                            'bg-warn-100 text-warn-800': tag.tone === 'warn',
                                            'bg-info-100 text-info-800': tag.tone === 'info',
                                        }"
                                        x-text="tag.label"
                                    ></span>
                                </template>
                            </div>
                            <div class="mt-3">
                                <label class="label text-xs" :for="'correct_' + punch.type">Correct time</label>
                                <input
                                    :id="'correct_' + punch.type"
                                    type="time"
                                    class="input tabular-nums @error('requested_time') input-error @enderror"
                                    :disabled="punch.pending"
                                    :value="inputValue(punch)"
                                    @focus="activatePunch(punch)"
                                    @input="activatePunch(punch, $event.target.value)"
                                >
                            </div>
                        </div>
                    </template>
                </div>
                @if ($errors->has('punch_type') || $errors->has('requested_time'))
                    <div class="mt-3 space-y-1">
                        @error('punch_type')<p class="error-text">{{ $message }}</p>@enderror
                        @error('requested_time')<p class="error-text">{{ $message }}</p>@enderror
                    </div>
                @endif
            </div>

            <div x-show="loading" class="text-sm text-muted">Loading attendance for selected date…</div>

            <div>
                <label class="label" for="reason">Reason for correction</label>
                <textarea id="reason" class="textarea @error('reason') input-error @enderror" name="reason" rows="4" required placeholder="Explain why this entry needs to be corrected (e.g., forgot to scan, station error, meeting off-site).">{{ old('reason') }}</textarea>
                @error('reason')<p class="error-text">{{ $message }}</p>@enderror
            </div>

            <div class="flex flex-wrap gap-2 pt-1">
                <button type="submit" class="btn-primary">Submit request</button>
                <a href="{{ route('employee.attendance-corrections.index') }}" class="btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
