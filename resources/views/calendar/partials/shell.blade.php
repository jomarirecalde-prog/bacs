{{--
    The calendar surface shared by the admin and employee pages.

    Expects: $calendarRoute, $view, $focus, $period, $today, $eventCount,
             $canManage, $modalEvents, $liveUrl, and the view-specific collections.
    Event details are embedded as JSON that the server already filtered, so the
    modal never needs a second request and cannot reveal unauthorised events.
--}}
<div class="cal-shell"
     x-data="calendarLive({
         events: @js($modalEvents),
         eventCount: {{ (int) $eventCount }},
         view: @js($view),
         focus: @js($focus->toDateString()),
         start: @js($period['start']->toDateString()),
         end: @js($period['end']->toDateString()),
         liveUrl: @js($liveUrl ?? ''),
         type: @js(optional($typeFilter)->value),
     })">
    @include('calendar.partials.toolbar')

    <div x-ref="body">
        @if ($view === 'month')
            @include('calendar.partials.month')
        @elseif ($view === 'week')
            @include('calendar.partials.week')
        @elseif ($view === 'day')
            @include('calendar.partials.day')
        @else
            @include('calendar.partials.agenda')
        @endif
    </div>

    @include('calendar.partials.legend')
    @include('calendar.partials.details-modal')
</div>
