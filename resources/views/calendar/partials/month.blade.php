@php
    $maxChips = 3;
@endphp

<div class="cal-weekhead">
    @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
        <div class="cal-weekday">
            <span class="hidden sm:inline">{{ $weekday }}</span>
            <span class="sm:hidden">{{ substr($weekday, 0, 1) }}</span>
        </div>
    @endforeach
</div>

<div class="cal-grid">
    @foreach ($weeks as $week)
        @foreach ($week as $cell)
            @php
                $events = $cell['events'];
                $overflow = max(0, $events->count() - $maxChips);
            @endphp
            <div class="cal-cell group
                        {{ $cell['in_month'] ? '' : 'cal-cell-out' }}
                        {{ $cell['is_today'] ? 'cal-cell-today' : ($cell['holiday'] ? 'cal-cell-holiday' : '') }}">
                <div class="flex items-start justify-between gap-1">
                    <span class="cal-daynum {{ $cell['is_today'] ? 'cal-daynum-today' : ($cell['in_month'] ? '' : 'cal-daynum-out') }}">
                        {{ $cell['day'] }}
                    </span>
                    @if ($canManage)
                        <a href="{{ route('admin.calendar.events.create', ['date' => $cell['date']]) }}"
                           {{-- Always tappable on touch devices; hover-revealed on pointer devices. --}}
                           class="shrink-0 rounded-md p-0.5 text-faint transition hover:bg-brand-100 hover:text-brand-700 focus-visible:opacity-100 sm:opacity-0 sm:group-hover:opacity-100"
                           title="Add event on {{ \App\Support\ManilaTime::parse($cell['date'])->format('M j') }}"
                           aria-label="Add event on {{ \App\Support\ManilaTime::parse($cell['date'])->format('F j, Y') }}">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </a>
                    @endif
                </div>

                @if ($cell['holiday'])
                    <span class="cal-holiday-tag" title="{{ $cell['holiday']->name }}">★ {{ $cell['holiday']->name }}</span>
                @endif

                <div class="flex min-w-0 flex-col gap-1">
                    @foreach ($events->take($maxChips) as $event)
                        <x-calendar.chip :event="$event" />
                    @endforeach
                    @if ($overflow > 0)
                        <a href="{{ route($calendarRoute, ['view' => 'day', 'date' => $cell['date']]) }}" class="cal-more hover:text-brand-700 hover:underline">
                            +{{ $overflow }} more
                        </a>
                    @endif
                </div>
            </div>
        @endforeach
    @endforeach
</div>
