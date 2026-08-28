@extends('layouts.app')

@section('title', 'Manage Events')
@section('page-title', 'Calendar Event Management')
@section('page-subtitle', 'Search, review, and maintain every company event')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="pill-tabs">
            <a href="{{ route('admin.calendar.index') }}" class="pill-tab">Calendar</a>
            <a href="{{ route('admin.calendar.events.index') }}" class="pill-tab pill-tab-active">Manage Events</a>
        </div>
        @if ($canManage)
            <a href="{{ route('admin.calendar.events.create') }}" class="btn-primary btn-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Add Event
            </a>
        @endif
    </div>

    <form method="GET" action="{{ route('admin.calendar.events.index') }}" class="filter-bar mb-4">
        <div class="sm:col-span-2">
            <label for="q" class="label">Search</label>
            <input id="q" type="search" name="q" value="{{ request('q') }}" class="input" placeholder="Title, description, or location">
        </div>
        <div>
            <label for="type" class="label">Event type</label>
            <select id="type" name="type" class="select">
                <option value="">All types</option>
                @foreach (\App\Enums\CalendarEventType::cases() as $option)
                    <option value="{{ $option->value }}" @selected($type === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="status" class="label">Status</label>
            <select id="status" name="status" class="select">
                <option value="">All statuses</option>
                @foreach (\App\Enums\EventStatus::cases() as $option)
                    <option value="{{ $option->value }}" @selected($status === $option)>{{ $option->label() }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="from" class="label">From</label>
            <input id="from" type="date" name="from" value="{{ request('from') }}" class="input">
        </div>
        <div>
            <label for="to" class="label">To</label>
            <input id="to" type="date" name="to" value="{{ request('to') }}" class="input">
        </div>
        <div class="flex items-end gap-2">
            <button type="submit" class="btn-secondary btn-sm">Apply</button>
            <a href="{{ route('admin.calendar.events.index') }}" class="btn-outline btn-sm">Reset</a>
        </div>
    </form>

    <div class="card">
        @if ($events->isEmpty())
            <x-empty-state
                icon="calendar"
                title="No events found"
                message="No calendar events match the current filters." />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Type</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Audience</th>
                            <th>Attendance Effect</th>
                            <th>Created By</th>
                            <th>Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($events as $event)
                            @php $tone = $event->event_type->tone(); @endphp
                            <tr class="{{ $event->affectsAttendance() ? 'row-featured' : '' }}">
                                <td>
                                    <div class="font-semibold text-ink">{{ $event->title }}</div>
                                    @if ($event->location)
                                        <div class="text-xs text-muted">{{ $event->location }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge-{{ $tone }}">
                                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $event->event_type->iconPath() }}"/></svg>
                                        {{ $event->event_type->shortLabel() }}
                                    </span>
                                </td>
                                <td>
                                    <div class="font-medium text-ink">{{ $event->dateLabel() }}</div>
                                    @if ($event->isMultiDay())
                                        <div class="text-xs text-muted">Multi-day</div>
                                    @endif
                                </td>
                                <td class="text-muted">{{ $event->timeLabel() }}</td>
                                <td>
                                    <span class="chip">{{ $event->audienceSummary() }}</span>
                                </td>
                                <td>
                                    @if ($event->attendance_effect)
                                        <span class="badge-{{ $event->attendance_effect->tone() }}">{{ $event->attendance_effect->label() }}</span>
                                    @else
                                        <span class="text-xs text-faint">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="text-sm text-ink-soft">{{ $event->creator?->name ?? 'System' }}</div>
                                    <div class="text-xs text-muted">{{ \App\Support\ManilaTime::formatDate($event->created_at) }}</div>
                                </td>
                                <td>
                                    <span class="badge-{{ $event->status->tone() }}">{{ $event->status->label() }}</span>
                                </td>
                                <td>
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('admin.calendar.events.show', $event) }}" class="btn-outline btn-sm">View</a>
                                        @if ($canManage)
                                            <a href="{{ route('admin.calendar.events.edit', $event) }}" class="btn-outline-info btn-sm">Edit</a>
                                            @include('admin.calendar.events.partials.delete-button', ['event' => $event])
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($events->hasPages())
                <div class="card-footer">{{ $events->links() }}</div>
            @endif
        @endif
    </div>
@endsection
