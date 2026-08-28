{{-- Seven stacked day columns on desktop, a single readable list on mobile. --}}
<div class="grid grid-cols-1 divide-y divide-line/70 sm:grid-cols-7 sm:divide-y-0">
    @foreach ($weekDays as $day)
        <div class="cal-daycol {{ $day['is_today'] ? 'bg-brand-50/50' : ($day['holiday'] ? 'bg-brand-50/30' : '') }}">
            <div class="cal-daycol-head">
                <div class="flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wide text-muted">{{ $day['weekday'] }}</span>
                    <span class="cal-daynum {{ $day['is_today'] ? 'cal-daynum-today' : '' }}">{{ $day['day'] }}</span>
                </div>
                @if ($canManage)
                    <a href="{{ route('admin.calendar.events.create', ['date' => $day['date']]) }}"
                       class="rounded-md p-0.5 text-faint transition hover:bg-brand-100 hover:text-brand-700"
                       aria-label="Add event on {{ \App\Support\ManilaTime::parse($day['date'])->format('F j, Y') }}">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    </a>
                @endif
            </div>

            @if ($day['holiday'])
                <div class="rounded-lg border border-brand-200 bg-brand-50 px-2 py-1.5">
                    <div class="cal-holiday-tag">★ Holiday</div>
                    <div class="truncate text-[11px] font-semibold text-brand-800">{{ $day['holiday']->name }}</div>
                </div>
            @endif

            <div class="flex flex-col gap-1.5">
                @forelse ($day['events'] as $event)
                    <x-calendar.chip :event="$event" />
                @empty
                    <p class="px-1 py-2 text-[11px] text-faint">No events</p>
                @endforelse
            </div>
        </div>
    @endforeach
</div>
