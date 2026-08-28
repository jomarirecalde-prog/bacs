@extends('layouts.app')

@php
    $type = $event->event_type;
    $tone = $type->tone();
@endphp

@section('title', $event->title)
@section('page-title', 'Event Details')
@section('page-subtitle', $type->label())

@section('content')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('admin.calendar.events.index') }}" class="btn-outline btn-sm">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            Back to events
        </a>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.calendar.index', ['date' => $event->start_date->toDateString()]) }}" class="btn-outline-info btn-sm">Show on calendar</a>
            @if ($canManage)
                <a href="{{ route('admin.calendar.events.edit', $event) }}" class="btn-primary btn-sm">Edit event</a>
                @include('admin.calendar.events.partials.delete-button', ['event' => $event])
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            <div class="card card-accent-{{ $tone === 'neutral' ? 'brand' : $tone }}">
                <div class="card-body">
                    @if ($event->isNonWorking())
                        <div class="cal-banner cal-banner-brand mb-4">Holiday / No Attendance Required</div>
                    @elseif ($type === \App\Enums\CalendarEventType::Meeting)
                        <div class="cal-banner cal-banner-info mb-4">Company Meeting</div>
                    @elseif ($type === \App\Enums\CalendarEventType::Announcement)
                        <div class="cal-banner cal-banner-warn mb-4">Announcement</div>
                    @elseif ($type === \App\Enums\CalendarEventType::CompanyEvent)
                        <div class="cal-banner cal-banner-gold mb-4">Company Event</div>
                    @endif

                    <div class="flex items-start gap-3">
                        <span class="cal-event-icon h-11 w-11 cal-event-icon-{{ $tone }}">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $type->iconPath() }}"/></svg>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-xl font-extrabold tracking-tight text-ink">{{ $event->title }}</h2>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="badge-{{ $tone }}">{{ $type->label() }}</span>
                                <span class="badge-{{ $event->status->tone() }}">{{ $event->status->label() }}</span>
                                @if ($event->isMultiDay())
                                    <span class="chip">Multi-day</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-3 border-t border-line pt-4 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-muted">Date</dt>
                            <dd class="font-semibold text-ink">{{ $event->dateLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Time</dt>
                            <dd class="font-semibold text-ink">{{ $event->timeLabel() }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Location</dt>
                            <dd class="font-semibold text-ink">{{ $event->location ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-muted">Attendance effect</dt>
                            <dd class="font-semibold text-ink">{{ $event->attendance_effect?->label() ?? 'Not applicable' }}</dd>
                        </div>
                    </dl>

                    @if ($event->description)
                        <div class="mt-5 border-t border-line pt-4">
                            <h3 class="text-xs font-bold uppercase tracking-wide text-muted">Description</h3>
                            <p class="mt-1.5 whitespace-pre-line text-sm text-ink-soft">{{ $event->description }}</p>
                        </div>
                    @endif

                    @if ($event->additional_instructions)
                        <div class="alert-gold mt-4">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gold-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>
                                <strong class="block font-bold">Additional instructions</strong>
                                <span class="whitespace-pre-line">{{ $event->additional_instructions }}</span>
                            </span>
                        </div>
                    @endif
                </div>
            </div>

            @if ($event->affectsAttendance())
                <div class="alert-warning">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                    <span>
                        <strong class="block font-bold">This event affects attendance calculations.</strong>
                        {{ $event->dateLabel() }} is treated as <strong>{{ $event->attendance_effect->label() }}</strong>.
                        Employees are not marked absent for these dates, and the DTR shows the holiday name.
                        Editing or deleting this event changes future attendance calculations for that range.
                    </span>
                </div>
            @endif
        </div>

        <div class="space-y-4">
            <div class="card card-accent-info">
                <div class="card-header">
                    <h2 class="card-title">Audience</h2>
                    <span class="badge-{{ $event->audience_type->tone() }}">{{ $event->audience_type->label() }}</span>
                </div>
                <div class="card-body">
                    @if ($event->audience_type === \App\Enums\EventAudience::All)
                        <p class="text-sm text-ink-soft">Visible to all active employees.</p>
                    @elseif ($event->audience_type === \App\Enums\EventAudience::Departments)
                        <ul class="space-y-1.5">
                            @forelse ($event->departments as $department)
                                <li class="flex items-center gap-2 text-sm text-ink">
                                    <svg class="h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5"/></svg>
                                    {{ $department->name }}
                                </li>
                            @empty
                                <li class="text-sm text-muted">No departments selected.</li>
                            @endforelse
                        </ul>
                    @else
                        <ul class="max-h-64 space-y-1.5 overflow-y-auto">
                            @forelse ($event->employees as $employee)
                                <li class="flex items-center gap-2 text-sm text-ink">
                                    <svg class="h-4 w-4 shrink-0 text-gold-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                    <span class="truncate">{{ $employee->fullName() }}</span>
                                </li>
                            @empty
                                <li class="text-sm text-muted">No employees selected.</li>
                            @endforelse
                        </ul>
                    @endif
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Record</h2>
                </div>
                <div class="card-body">
                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Created by</dt>
                            <dd class="text-right font-semibold text-ink">{{ $event->creator?->name ?? 'System' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Date created</dt>
                            <dd class="text-right text-ink-soft">{{ \App\Support\ManilaTime::formatDateTime($event->created_at) }}</dd>
                        </div>
                        @if ($event->updater)
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted">Last updated by</dt>
                                <dd class="text-right font-semibold text-ink">{{ $event->updater->name }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-muted">Last updated</dt>
                                <dd class="text-right text-ink-soft">{{ \App\Support\ManilaTime::formatDateTime($event->updated_at) }}</dd>
                            </div>
                        @endif
                        <div class="flex justify-between gap-3">
                            <dt class="text-muted">Notification</dt>
                            <dd class="text-right text-ink-soft">
                                {{ $event->notified_at ? 'Sent '.\App\Support\ManilaTime::formatDate($event->notified_at) : 'Not sent' }}
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>
        </div>
    </div>
@endsection
