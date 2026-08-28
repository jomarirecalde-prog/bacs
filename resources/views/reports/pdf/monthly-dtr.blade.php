<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Monthly DTR - {{ $employee->fullName() }}</title>
    @include('reports.partials.print-theme')
</head>
<body>
    <div class="doc-header">
        <h1>{{ $company ?? config('app.name') }}</h1>
        <div class="muted">{{ $address ?? '' }}</div>
    </div>
    <div class="doc-rule-gold"></div>

    <h3>DAILY TIME RECORD</h3>
    <p class="meta">
        Employee: <strong>{{ $employee->fullName() }}</strong> ({{ $employee->employee_number }})<br>
        Department: {{ $employee->department?->name }} · Position: {{ $employee->position }}<br>
        Period: {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}<br>
        <span class="muted">Generated: {{ now()->format('F j, Y g:i A') }} (Asia/Manila)</span>
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Late</th><th>Undertime</th><th>Overtime</th><th>Status</th><th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    @php $holiday = app(\App\Services\HolidayResolver::class)->forDate(optional($row->attendance_date)->toDateString() ?: now()->toDateString(), $employee); @endphp
                    <td>
                        {{ optional($row->attendance_date)->format('M d, Y D') }}
                        @if ($holiday)<br><span class="muted">{{ $holiday->name }}</span>@endif
                    </td>
                    <td>{{ $row->time_in?->format('h:i A') ?? '' }}</td>
                    <td>{{ $row->time_out?->format('h:i A') ?? '' }}</td>
                    <td class="num">{{ $row->totalHoursLabel() }}</td>
                    <td class="num">{{ $row->late_minutes }}</td>
                    <td class="num">{{ $row->undertime_minutes }}</td>
                    <td class="num">{{ $row->overtimeHoursLabel() }}</td>
                    <td>{{ $row->status?->label() }}</td>
                    <td>{{ $row->remarks }}{{ $row->is_edited ? ' (edited)' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="sign">
        <div><div class="line">Employee Signature</div></div>
        <div><div class="line">Supervisor / Admin Signature</div></div>
    </div>

    <p class="certify">I certify that the above is a true and correct report of the hours of work performed.</p>
</body>
</html>
