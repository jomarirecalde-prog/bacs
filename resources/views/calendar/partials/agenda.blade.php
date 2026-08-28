@forelse ($agenda as $row)
    <div class="cal-agenda-row {{ $row['is_past'] ? 'opacity-70' : '' }} {{ $row['is_today'] ? 'bg-brand-50/40' : '' }}">
        <div class="cal-agenda-date">
            <div class="flex items-center gap-2">
                <span class="cal-daynum {{ $row['is_today'] ? 'cal-daynum-today' : '' }}">{{ $row['day'] }}</span>
                <span class="text-xs font-bold uppercase tracking-wide text-muted">{{ $row['short'] }}</span>
                @if ($row['is_today'])
                    <span class="badge-brand">Today</span>
                @endif
            </div>
            <div class="text-xs text-muted sm:mt-1">{{ $row['label'] }}</div>
            @if ($row['holiday'])
                <div class="cal-holiday-tag mt-0.5">★ {{ $row['holiday']->name }}</div>
            @endif
        </div>

        <div class="flex min-w-0 flex-1 flex-col gap-2">
            @foreach ($row['events'] as $event)
                <x-calendar.event-card :event="$event" />
            @endforeach
        </div>
    </div>
@empty
    <x-empty-state
        icon="calendar"
        title="No events this period"
        message="Nothing is scheduled for {{ $period['label'] }}. Use the arrows to browse other months." />
@endforelse
