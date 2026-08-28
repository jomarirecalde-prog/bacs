@extends('layouts.app')

@php
    $editing = $event->exists;
    $action = $editing ? route('admin.calendar.events.update', $event) : route('admin.calendar.events.store');

    $effectTypes = collect($types)
        ->filter(fn ($type) => $type->supportsAttendanceEffect())
        ->map(fn ($type) => $type->value)
        ->values();

    $currentType = old('event_type', $event->event_type?->value ?? \App\Enums\CalendarEventType::Meeting->value);
    $currentAudience = old('audience_type', $event->audience_type?->value ?? \App\Enums\EventAudience::All->value);
    $allDay = (bool) old('is_all_day', $event->is_all_day ?? true);
@endphp

@section('title', $editing ? 'Edit Event' : 'Add Event')
@section('page-title', $editing ? 'Edit Calendar Event' : 'Add Calendar Event')
@section('page-subtitle', $editing ? $event->title : 'Create a holiday, meeting, announcement, or company event')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.calendar.events.index') }}" class="btn-outline btn-sm">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to events
        </a>
    </div>

    <form method="POST" action="{{ $action }}"
          x-data="{
              type: @js($currentType),
              allDay: @js($allDay),
              audience: @js($currentAudience),
              employeeSearch: '',
              selectedEmployees: @js(array_map('strval', (array) $selectedEmployees)),
              effectTypes: @js($effectTypes),
              get supportsEffect() { return this.effectTypes.includes(this.type); },
          }">
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            {{-- ------------------------------------------------ Event details --}}
            <div class="space-y-4 lg:col-span-2">
                <div class="card card-accent-brand">
                    <div class="card-header">
                        <h2 class="card-title">Event details</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label for="title" class="label">Event title <span class="text-critical-600">*</span></label>
                            <input id="title" type="text" name="title" value="{{ old('title', $event->title) }}"
                                   class="input @error('title') input-error @enderror" required maxlength="160"
                                   placeholder="e.g. National Heroes Day">
                            @error('title') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="label">Event type <span class="text-critical-600">*</span></label>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                @foreach ($types as $type)
                                    <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border px-3 py-2.5 transition"
                                           :class="type === @js($type->value)
                                               ? 'border-brand-400 bg-brand-50 ring-1 ring-brand-300'
                                               : 'border-line bg-surface hover:border-brand-200 hover:bg-brand-50/40'">
                                        <input type="radio" name="event_type" value="{{ $type->value }}"
                                               x-model="type" class="radio mt-0.5" required>
                                        <span class="min-w-0">
                                            <span class="flex items-center gap-1.5 text-sm font-semibold text-ink">
                                                <svg class="h-4 w-4 shrink-0 text-{{ $type->tone() === 'neutral' ? 'muted' : $type->tone().'-600' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $type->iconPath() }}"/></svg>
                                                {{ $type->label() }}
                                            </span>
                                            @if ($type->supportsAttendanceEffect())
                                                <span class="mt-0.5 block text-[11px] text-muted">Can affect attendance</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('event_type') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="description" class="label">Description</label>
                            <textarea id="description" name="description" rows="3" class="textarea @error('description') input-error @enderror"
                                      placeholder="What is this event about?">{{ old('description', $event->description) }}</textarea>
                            @error('description') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                {{-- ---------------------------------------------- Date & time --}}
                <div class="card card-accent-info">
                    <div class="card-header">
                        <h2 class="card-title">Date &amp; time</h2>
                        <span class="text-xs text-muted">Philippine Standard Time</span>
                    </div>
                    <div class="card-body space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="start_date" class="label">Start date <span class="text-critical-600">*</span></label>
                                <input id="start_date" type="date" name="start_date"
                                       value="{{ old('start_date', $event->start_date?->toDateString()) }}"
                                       class="input @error('start_date') input-error @enderror" required
                                       x-ref="startDate"
                                       @change="if ($refs.endDate.value < $refs.startDate.value) $refs.endDate.value = $refs.startDate.value">
                                @error('start_date') <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="end_date" class="label">End date <span class="text-critical-600">*</span></label>
                                <input id="end_date" type="date" name="end_date"
                                       value="{{ old('end_date', $event->end_date?->toDateString()) }}"
                                       class="input @error('end_date') input-error @enderror" required x-ref="endDate">
                                <p class="hint">Set a later end date for multi-day events.</p>
                                @error('end_date') <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <label class="flex cursor-pointer items-center gap-2.5 rounded-xl border border-line bg-canvas/60 px-3 py-2.5">
                            <input type="checkbox" name="is_all_day" value="1" x-model="allDay" class="checkbox">
                            <span class="text-sm font-semibold text-ink">All-day event</span>
                            <span class="text-xs text-muted">No specific start or end time</span>
                        </label>

                        <div x-show="!allDay" x-cloak class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <label for="start_time" class="label">Start time</label>
                                <input id="start_time" type="time" name="start_time"
                                       value="{{ old('start_time', $event->start_time ? substr((string) $event->start_time, 0, 5) : '09:00') }}"
                                       class="input @error('start_time') input-error @enderror" :disabled="allDay">
                                @error('start_time') <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="end_time" class="label">End time</label>
                                <input id="end_time" type="time" name="end_time"
                                       value="{{ old('end_time', $event->end_time ? substr((string) $event->end_time, 0, 5) : '11:00') }}"
                                       class="input @error('end_time') input-error @enderror" :disabled="allDay">
                                @error('end_time') <p class="error-text">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label for="location" class="label">Location</label>
                            <input id="location" type="text" name="location" value="{{ old('location', $event->location) }}"
                                   class="input @error('location') input-error @enderror" maxlength="160"
                                   placeholder="e.g. Main Conference Room">
                            @error('location') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="additional_instructions" class="label">Additional instructions</label>
                            <textarea id="additional_instructions" name="additional_instructions" rows="2"
                                      class="textarea @error('additional_instructions') input-error @enderror"
                                      placeholder="e.g. Bring your project reports.">{{ old('additional_instructions', $event->additional_instructions) }}</textarea>
                            @error('additional_instructions') <p class="error-text">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

                @include('admin.calendar.events.partials.audience-fields')
            </div>

            {{-- ------------------------------------------- Sidebar settings --}}
            <div class="space-y-4">
                {{-- Attendance effect only applies to holiday-style days. --}}
                <div x-show="supportsEffect" x-cloak class="card card-accent-gold">
                    <div class="card-header">
                        <h2 class="card-title">Attendance effect</h2>
                        <span class="badge-featured">Affects DTR</span>
                    </div>
                    <div class="card-body space-y-3">
                        <p class="text-xs text-muted">
                            Controls how this date is treated by attendance monitoring, the DTR, and reports for the selected audience only. Company-wide holidays should use “All employees”.
                        </p>
                        @foreach ($effects as $effect)
                            <label class="flex cursor-pointer items-start gap-2.5 rounded-xl border border-line bg-surface px-3 py-2.5 transition hover:border-gold-300 hover:bg-gold-50/50">
                                <input type="radio" name="attendance_effect" value="{{ $effect->value }}"
                                       class="radio mt-0.5" :disabled="!supportsEffect"
                                       @checked(old('attendance_effect', $event->attendance_effect?->value) === $effect->value)>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-ink">{{ $effect->label() }}</span>
                                    <span class="mt-0.5 block text-[11px] text-muted">{{ $effect->description() }}</span>
                                </span>
                            </label>
                        @endforeach
                        @error('attendance_effect') <p class="error-text">{{ $message }}</p> @enderror

                        <div class="alert-info">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>Employees are never marked absent on a non-working day. Historical attendance records are left untouched.</span>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h2 class="card-title">Publishing</h2>
                    </div>
                    <div class="card-body space-y-4">
                        <div>
                            <label for="status" class="label">Status <span class="text-critical-600">*</span></label>
                            <select id="status" name="status" class="select @error('status') input-error @enderror" required>
                                @foreach ($statuses as $option)
                                    <option value="{{ $option->value }}" @selected(old('status', $event->status?->value ?? \App\Enums\EventStatus::Published->value) === $option->value)>
                                        {{ $option->label() }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="hint">Only published events appear on employee calendars.</p>
                            @error('status') <p class="error-text">{{ $message }}</p> @enderror
                        </div>

                        <div class="rounded-xl border border-warn-200 bg-warn-50/60 px-3 py-2.5">
                            <p class="text-sm font-semibold text-ink">Real-time notifications</p>
                            <p class="mt-0.5 text-[11px] text-muted">The selected audience is notified immediately when this event is published, updated, or cancelled. Unread items stay in the bell until marked as read.</p>
                            <label class="mt-2 flex cursor-pointer items-start gap-2">
                                <input type="checkbox" name="notify_audience" value="1" class="checkbox mt-0.5"
                                       @checked(old('notify_audience', $event->exists ? $event->notify_audience : true))>
                                <span class="text-[11px] text-ink">Show a toast popup for this announcement</span>
                            </label>
                        </div>
                        @if ($editing && $event->notified_at)
                            <p class="hint">Already announced on {{ \App\Support\ManilaTime::formatDateTime($event->notified_at) }}.</p>
                        @endif
                    </div>
                </div>

                <div class="card">
                    <div class="card-body flex flex-col gap-2">
                        <button type="submit" class="btn-primary btn-block">
                            {{ $editing ? 'Save changes' : 'Create event' }}
                        </button>
                        <a href="{{ route('admin.calendar.events.index') }}" class="btn-outline btn-block">Cancel</a>
                    </div>
                </div>
            </div>
        </div>
    </form>
@endsection
