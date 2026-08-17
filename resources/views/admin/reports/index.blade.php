@extends('layouts.app')

@section('title', 'Reports')
@section('page-title', 'Reports')
@section('page-subtitle', 'Generate official attendance reports')

@section('content')
<div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
    @foreach ([
        ['admin.reports.daily', 'Daily Attendance Report', 'Filter by date, department, employee, and status.'],
        ['admin.reports.monthly', 'Monthly DTR', 'Complete monthly DTR for a selected employee.'],
        ['admin.reports.late', 'Late Employees Report', 'Employees who arrived after the grace period.'],
        ['admin.reports.absences', 'Absence Report', 'Employees marked absent on workdays.'],
        ['admin.reports.overtime', 'Overtime Report', 'Employees with overtime hours.'],
        ['admin.reports.undertime', 'Undertime Report', 'Employees with undertime.'],
    ] as [$route, $title, $desc])
        <a href="{{ route($route) }}" class="card p-6 hover:border-brand-200 transition">
            <h3 class="font-bold">{{ $title }}</h3>
            <p class="mt-2 text-sm text-slate-500">{{ $desc }}</p>
            <span class="mt-4 inline-block text-sm font-semibold text-brand-700">Open report →</span>
        </a>
    @endforeach
</div>
@endsection
