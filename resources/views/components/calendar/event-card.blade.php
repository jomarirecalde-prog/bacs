@props(['event', 'showDate' => false])

@php
    $type = $event->event_type;
    $tone = $type->tone();
    $cancelled = $event->status === \App\Enums\EventStatus::Cancelled;
@endphp

<button type="button"
        @click.stop="show({{ $event->id }})"
        class="cal-event cal-event-{{ $tone }} {{ $cancelled ? 'opacity-70' : '' }}">
    <span class="cal-event-icon cal-event-icon-{{ $tone }}">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $type->iconPath() }}"/>
        </svg>
    </span>
    <span class="min-w-0 flex-1">
        <span class="flex flex-wrap items-center gap-2">
            <span class="cal-event-title {{ $cancelled ? 'line-through' : '' }}">{{ $event->title }}</span>
            <span class="badge-{{ $tone === 'neutral' ? 'neutral' : $tone }} shrink-0">{{ $type->shortLabel() }}</span>
            @if ($cancelled)
                <span class="badge-critical shrink-0">Cancelled</span>
            @elseif ($event->isNonWorking())
                <span class="badge-featured shrink-0">No Attendance</span>
            @endif
        </span>
        <span class="cal-event-meta">
            @if ($showDate)
                <span class="font-semibold text-ink-soft">{{ $event->dateLabel() }}</span>
            @endif
            <span class="inline-flex items-center gap-1">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                {{ $event->timeLabel() }}
            </span>
            @if ($event->location)
                <span class="inline-flex items-center gap-1">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    {{ $event->location }}
                </span>
            @endif
        </span>
    </span>
</button>
