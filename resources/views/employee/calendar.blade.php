@extends('layouts.app')

@section('title', 'Calendar')
@section('page-title', 'Company Calendar')
@section('page-subtitle', 'Holidays, meetings, and announcements for you · Philippine Standard Time')

@section('content')
    @php $calendarRoute = 'employee.calendar'; @endphp

    {{-- View-only surface: no create, edit, or delete controls anywhere on this page. --}}
    <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div class="inline-flex items-center gap-2 text-xs text-muted">
            <svg class="h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            You are viewing events shared with you. Select an event to see its full details.
        </div>
        <a href="{{ route('employee.dtr') }}" class="btn-outline btn-sm">View my DTR</a>
    </div>

    @include('calendar.partials.shell')
@endsection
