@extends('layouts.app')

@section('title', 'Leave Calendar / History')
@section('page-title', 'Leave Calendar / History')
@section('page-subtitle', DateTime::createFromFormat('!m', $month)->format('F').' '.$year)

@section('content')
@php
    $start = \Carbon\Carbon::create($year, $month, 1);
    $daysInMonth = $start->daysInMonth;
    $lead = (int) $start->copy()->startOfMonth()->dayOfWeekIso - 1;
    $byDate = [];
    foreach ($applications as $application) {
        $cursor = $application->start_date->copy();
        while ($cursor->lte($application->end_date)) {
            if ((int) $cursor->month === (int) $month && (int) $cursor->year === (int) $year) {
                $byDate[$cursor->toDateString()][] = $application;
            }
            $cursor->addDay();
        }
    }
@endphp
<div class="space-y-6">
    <form class="filter-bar">
        <div>
            <label class="label" for="cal-month">Month</label>
            <select id="cal-month" name="month" class="select">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($month == $m)>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
                @endfor
            </select>
        </div>
        <div>
            <label class="label" for="cal-year">Year</label>
            <select id="cal-year" name="year" class="select">
                @for ($y = now()->year + 1; $y >= now()->year - 4; $y--)
                    <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                @endfor
            </select>
        </div>
        <button class="btn-primary" type="submit">View</button>
        <a href="{{ route('employee.leave.apply') }}" class="btn-secondary">Apply for Leave</a>
    </form>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="card overflow-hidden xl:col-span-2">
            <div class="grid grid-cols-7 border-b border-line bg-canvas text-center text-[11px] font-bold uppercase tracking-wide text-muted">
                @foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d)
                    <div class="px-1 py-2">{{ $d }}</div>
                @endforeach
            </div>
            <div class="grid grid-cols-7">
                @for ($i = 0; $i < $lead; $i++)
                    <div class="min-h-20 border-b border-r border-line bg-canvas/40"></div>
                @endfor
                @for ($day = 1; $day <= $daysInMonth; $day++)
                    @php $date = sprintf('%04d-%02d-%02d', $year, $month, $day); $items = $byDate[$date] ?? []; @endphp
                    <div class="min-h-20 border-b border-r border-line p-1.5 {{ $items ? 'bg-brand-50/60' : '' }}">
                        <div class="text-xs font-bold text-ink">{{ $day }}</div>
                        @foreach (array_slice($items, 0, 2) as $item)
                            <a href="{{ route('employee.leave.show', $item) }}" class="mt-0.5 block truncate rounded bg-white px-1 py-0.5 text-[10px] font-semibold {{ $item->status->badgeClass() }}">{{ $item->leaveTypeLabel() }}</a>
                        @endforeach
                    </div>
                @endfor
            </div>
        </div>
        <div class="space-y-4">
            <div class="card card-accent-info">
                <div class="card-header"><h3 class="card-title">Balances ({{ $year }})</h3></div>
                <dl class="divide-y divide-line px-5 text-sm">
                    @foreach ($balances as $code => $row)
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="capitalize text-muted">{{ str_replace('_', ' ', $code) }}</dt>
                            <dd class="font-bold tabular-nums">{{ $row['remaining'] }} left</dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        </div>
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h3 class="card-title">Leave history this month</h3></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr><th>No.</th><th>Type</th><th>Dates</th><th>Days</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($applications as $application)
                        <tr>
                            <td class="font-semibold">{{ $application->application_number }}</td>
                            <td>{{ $application->leaveTypeLabel() }}</td>
                            <td>{{ $application->dateRangeLabel() }}</td>
                            <td>{{ rtrim(rtrim(number_format((float) $application->requested_days, 1), '0'), '.') }}</td>
                            <td><span class="{{ $application->status->badgeClass() }}">{{ $application->status->label() }}</span></td>
                            <td class="text-right"><a class="btn-outline btn-sm" href="{{ route('employee.leave.show', $application) }}">View</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-0"><x-empty-state title="No leave this month" message="Approved and pending leave will appear on this calendar." icon="calendar" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
