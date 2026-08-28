@extends('layouts.app')

@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-subtitle', 'Generate official attendance reports')

@section('content')
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ([
        ['admin.reports.daily', 'Daily Attendance Report', 'Filter by date, department, employee, and status.', 'brand', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
        ['admin.reports.monthly', 'Monthly DTR', 'Complete monthly DTR for a selected employee.', 'gold', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
        ['admin.reports.late', 'Late Employees Report', 'Employees who arrived after the grace period.', 'warn', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['admin.reports.absences', 'Absence Report', 'Employees marked absent on workdays.', 'critical', 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z'],
        ['admin.reports.overtime', 'Overtime Report', 'Employees with overtime hours.', 'info', 'M13 7h8m0 0v8m0-8l-8 8-4-4-6 6'],
        ['admin.reports.undertime', 'Undertime Report', 'Employees with undertime.', 'warn', 'M13 17h8m0 0V9m0 8l-8-8-4 4-6-6'],
    ] as [$route, $title, $desc, $tone, $iconPath])
        @php
            $accent = [
                'brand' => ['card-accent-brand', 'stat-icon-brand', 'text-brand-700'],
                'info' => ['card-accent-info', 'stat-icon-info', 'text-info-700'],
                'gold' => ['card-accent-gold', 'stat-icon-gold', 'text-gold-700'],
                'warn' => ['card-accent-warn', 'stat-icon-warn', 'text-warn-700'],
                'critical' => ['border-t-2 border-t-critical-500', 'stat-icon-critical', 'text-critical-700'],
            ][$tone];
        @endphp
        <a href="{{ route($route) }}" class="card-interactive group {{ $accent[0] }} flex flex-col p-6">
            <div class="{{ $accent[1] }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $iconPath }}"/></svg>
            </div>
            <h3 class="mt-4 font-bold text-ink">{{ $title }}</h3>
            <p class="mt-1.5 flex-1 text-sm text-muted">{{ $desc }}</p>
            <span class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold {{ $accent[2] }}">
                Open report
                <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
            </span>
        </a>
    @endforeach
</div>
@endsection
