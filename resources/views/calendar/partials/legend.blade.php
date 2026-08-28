@php
    $toneDot = [
        'brand' => 'bg-brand-600',
        'info' => 'bg-info-600',
        'gold' => 'bg-gold-500',
        'warn' => 'bg-warn-400',
        'neutral' => 'bg-line-strong',
    ];
@endphp

<div class="cal-legend">
    <span class="text-[11px] font-bold uppercase tracking-wide text-faint">Legend</span>
    @foreach (\App\Enums\CalendarEventType::cases() as $type)
        <span class="cal-legend-item">
            <span class="cal-legend-dot {{ $toneDot[$type->tone()] }}"></span>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $type->iconPath() }}"/></svg>
            {{ $type->shortLabel() }}
        </span>
    @endforeach
</div>
