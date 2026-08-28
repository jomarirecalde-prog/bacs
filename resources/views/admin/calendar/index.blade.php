@extends('layouts.app')

@section('title', 'Calendar')
@section('page-title', 'Company Calendar')
@section('page-subtitle', 'Holidays, meetings, announcements, and company events · Philippine Standard Time')

@section('content')
    @php $calendarRoute = 'admin.calendar.index'; @endphp

    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <form method="GET" action="{{ route('admin.calendar.index') }}" class="filter-bar">
            <input type="hidden" name="view" value="{{ $view }}">
            <input type="hidden" name="date" value="{{ $focus->toDateString() }}">
            <div>
                <label for="type" class="label">Event type</label>
                <select id="type" name="type" class="select" onchange="this.form.submit()">
                    <option value="">All event types</option>
                    @foreach (\App\Enums\CalendarEventType::cases() as $type)
                        <option value="{{ $type->value }}" @selected($typeFilter === $type)>{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            @if ($typeFilter)
                <div class="flex items-end">
                    <a href="{{ route('admin.calendar.index', ['view' => $view, 'date' => $focus->toDateString()]) }}" class="btn-outline btn-sm">Clear</a>
                </div>
            @endif
        </form>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.calendar.events.index') }}" class="btn-outline-info btn-sm">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 10h16M4 14h10M4 18h7"/></svg>
                Manage Events
            </a>
            @if ($canManage)
                <a href="{{ route('admin.calendar.events.create', ['date' => $focus->toDateString()]) }}" class="btn-primary btn-sm">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Add Event
                </a>
            @endif
        </div>
    </div>

    @include('calendar.partials.shell')
@endsection
