@extends('layouts.app')

@section('title', 'My DTR')
@section('page-title', 'My Monthly DTR')

@section('content')
<form class="card p-4 flex flex-wrap gap-2 mb-6 print:hidden">
    <select name="month" class="input max-w-[160px]">
        @for ($m = 1; $m <= 12; $m++)
            <option value="{{ $m }}" @selected($month == $m)>{{ DateTime::createFromFormat('!m', $m)->format('F') }}</option>
        @endfor
    </select>
    <select name="year" class="input max-w-[120px]">
        @for ($y = now()->year; $y >= now()->year - 5; $y--)
            <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
        @endfor
    </select>
    <button class="btn-primary">View</button>
    <a class="btn-secondary" href="{{ route('employee.dtr.print', ['month' => $month, 'year' => $year]) }}" target="_blank">Print</a>
    <a class="btn-secondary" href="{{ route('employee.dtr.export', ['month' => $month, 'year' => $year, 'format' => 'pdf']) }}">PDF</a>
    <a class="btn-secondary" href="{{ route('employee.dtr.export', ['month' => $month, 'year' => $year, 'format' => 'excel']) }}">Excel</a>
    <a class="btn-secondary" href="{{ route('employee.dtr.export', ['month' => $month, 'year' => $year, 'format' => 'csv']) }}">CSV</a>
</form>
<div class="card overflow-hidden">
    <div class="px-5 py-4 border-b">
        <div class="font-bold">{{ $employee->fullName() }}</div>
        <div class="text-sm text-slate-500">{{ $employee->department?->name }} · {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Date</th><th>Time In</th><th>Time Out</th><th>Total Hours</th><th>Late</th><th>Undertime</th><th>Overtime</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td>{{ optional($row->attendance_date)->format('M d, Y D') }}</td>
                        <td>
                            {{ $row->time_in?->format('h:i A') ?? '—' }}
                            @if ($row->time_in_station_name)
                                <div class="text-[11px] text-slate-500">{{ $row->time_in_station_name }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $row->time_out?->format('h:i A') ?? '—' }}
                            @if ($row->time_out_station_name)
                                <div class="text-[11px] text-slate-500">{{ $row->time_out_station_name }}</div>
                            @endif
                        </td>
                        <td>{{ $row->totalHoursLabel() }}</td>
                        <td>{{ $row->late_minutes }}</td>
                        <td>{{ $row->undertime_minutes }}</td>
                        <td>{{ $row->overtimeHoursLabel() }}</td>
                        <td>
                            <x-status-badge :status="$row->status" />
                            @if ($row->is_edited)<div class="text-[11px] text-amber-700">Edited by admin</div>@endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
