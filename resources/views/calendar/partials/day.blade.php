<div class="p-4 sm:p-6">
    @if ($dayHoliday)
        <div class="alert-success mb-4">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118L2.58 9.11c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
            <span>
                <strong class="font-bold">{{ $dayHoliday->name }}</strong> — {{ $dayHoliday->effectLabel() }}.
                Employees are not marked absent on this date.
            </span>
        </div>
    @endif

    <div class="flex flex-col gap-2.5">
        @forelse ($dayEvents as $event)
            <x-calendar.event-card :event="$event" />
        @empty
            <x-empty-state
                icon="calendar"
                title="Nothing scheduled"
                message="There are no events on {{ $focus->format('F j, Y') }}." />
        @endforelse
    </div>

    @if ($canManage)
        <div class="mt-5 border-t border-line pt-4">
            <a href="{{ route('admin.calendar.events.create', ['date' => $focus->toDateString()]) }}" class="btn-primary btn-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add event on this date
            </a>
        </div>
    @endif
</div>
