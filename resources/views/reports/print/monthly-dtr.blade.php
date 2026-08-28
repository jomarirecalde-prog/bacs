<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Print DTR</title>
    @include('reports.partials.print-theme', ['font' => 'serif'])
</head>
<body>
    <p class="no-print"><button type="button" onclick="window.print()">Print this DTR</button></p>

    <div class="doc-header">
        <h1>{{ $company }}</h1>
        <div class="muted">{{ $address }}</div>
    </div>
    <div class="doc-rule-gold"></div>

    <h3>DAILY TIME RECORD</h3>
    <p class="meta">
        <strong>{{ $employee->fullName() }}</strong> ({{ $employee->employee_number }})<br>
        {{ $employee->department?->name }} · {{ $employee->position }}<br>
        {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th><th>Time In</th><th>Time Out</th><th>Hours</th><th>Late</th><th>Undertime</th><th>Overtime</th><th>Status</th>
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
                    <td>{{ $row->time_in?->format('h:i A') }}</td>
                    <td>{{ $row->time_out?->format('h:i A') }}</td>
                    <td class="num">{{ $row->totalHoursLabel() }}</td>
                    <td class="num">{{ $row->late_minutes }}</td>
                    <td class="num">{{ $row->undertime_minutes }}</td>
                    <td class="num">{{ $row->overtimeHoursLabel() }}</td>
                    <td>{{ $row->status?->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="sign">
        <div><div class="line">Employee Signature</div></div>
        <div><div class="line">Authorized Official</div></div>
    </div>
</body>
</html>
