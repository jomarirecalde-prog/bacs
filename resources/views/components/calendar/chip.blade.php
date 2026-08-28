@props(['event'])

@php
    $type = $event->event_type;
    $cancelled = $event->status === \App\Enums\EventStatus::Cancelled;
@endphp

{{-- Compact month-cell chip: colour rail + type icon + title, never colour alone. --}}
<button type="button"
        @click.stop="show({{ $event->id }})"
        class="cal-chip cal-chip-{{ $type->tone() }} {{ $cancelled ? 'opacity-60' : '' }}"
        title="{{ $type->label() }} · {{ $event->title }} · {{ $event->timeLabel() }}{{ $cancelled ? ' · Cancelled' : '' }}">
    <svg class="h-3 w-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $type->iconPath() }}"/>
    </svg>
    <span class="truncate {{ $cancelled ? 'line-through' : '' }}">{{ $event->title }}</span>
</button>
