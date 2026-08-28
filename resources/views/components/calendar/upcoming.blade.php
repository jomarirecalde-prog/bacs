@props([
    'events',
    'calendarUrl',
    'title' => 'Upcoming Events',
    'emptyMessage' => 'No upcoming holidays, meetings, or announcements.',
])

@php
    $today = \App\Support\ManilaTime::todayDate();
@endphp

<div class="card card-accent-gold">
    <div class="card-header">
        <h2 class="card-title">{{ $title }}</h2>
        <a href="{{ $calendarUrl }}" class="text-xs font-semibold text-info-700 hover:underline">View calendar</a>
    </div>

    @if ($events->isEmpty())
        <x-empty-state
            icon="calendar"
            title="Nothing scheduled"
            :message="$emptyMessage"
            class="py-10" />
    @else
        <ul class="divide-y divide-line">
            @foreach ($events as $event)
                @php
                    $type = $event->event_type;
                    $tone = $type->tone();
                    $daysAway = \App\Support\ManilaTime::parse($today)->diffInDays($event->start_date, false);
                @endphp
                <li>
                    <a href="{{ $calendarUrl }}{{ str_contains($calendarUrl, '?') ? '&' : '?' }}view=day&date={{ $event->start_date->toDateString() }}"
                       class="flex items-start gap-3 px-4 py-3 transition hover:bg-brand-50/50">
                        <span class="cal-event-icon cal-event-icon-{{ $tone }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $type->iconPath() }}"/></svg>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="flex flex-wrap items-center gap-1.5">
                                <span class="truncate text-sm font-bold text-ink">{{ $event->title }}</span>
                                <span class="badge-{{ $tone }} shrink-0">{{ $type->shortLabel() }}</span>
                            </span>
                            <span class="mt-0.5 block text-xs text-muted">
                                {{ $event->dateLabel() }} · {{ $event->timeLabel() }}
                            </span>
                        </span>
                        <span class="shrink-0 text-right">
                            @if ($daysAway <= 0)
                                <span class="badge-brand">Today</span>
                            @elseif ($daysAway === 1)
                                <span class="badge-warn">Tomorrow</span>
                            @else
                                <span class="text-[11px] font-semibold text-muted">in {{ (int) $daysAway }}d</span>
                            @endif
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="card-footer">
        <a href="{{ $calendarUrl }}" class="btn-outline-info btn-sm btn-block">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            {{ $slot->isEmpty() ? 'View Full Calendar' : $slot }}
        </a>
    </div>
</div>
