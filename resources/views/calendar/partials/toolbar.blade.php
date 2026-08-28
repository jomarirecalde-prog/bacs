@php
    $views = ['month' => 'Month', 'week' => 'Week', 'day' => 'Day', 'agenda' => 'Agenda'];
    $activeType = $typeFilter ?? null;
    $baseParams = $activeType ? ['type' => $activeType->value] : [];
@endphp

<div class="cal-toolbar">
    <div class="flex min-w-0 items-center gap-2">
        <div class="cal-nav">
            <a href="{{ route($calendarRoute, $baseParams + ['view' => $view, 'date' => $period['prev']]) }}"
               class="cal-nav-btn" aria-label="Previous {{ $view }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            </a>
            <a href="{{ route($calendarRoute, $baseParams + ['view' => $view, 'date' => $period['next']]) }}"
               class="cal-nav-btn" aria-label="Next {{ $view }}">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <a href="{{ route($calendarRoute, $baseParams + ['view' => $view, 'date' => $today]) }}" class="btn-outline btn-sm">Today</a>
        <h2 class="cal-period truncate">{{ $period['label'] }}</h2>
    </div>

    <div class="flex items-center gap-2">
        <span class="hidden text-xs font-semibold text-muted sm:inline" x-text="eventCount + ' ' + (eventCount === 1 ? 'event' : 'events')"></span>
        <div class="pill-tabs" role="tablist">
            @foreach ($views as $key => $label)
                <a href="{{ route($calendarRoute, $baseParams + ['view' => $key, 'date' => $focus->toDateString()]) }}"
                   class="pill-tab {{ $view === $key ? 'pill-tab-active' : '' }}"
                   role="tab" aria-selected="{{ $view === $key ? 'true' : 'false' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>
</div>
