<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Print DTR</title>
    <style>
        body { font-family: Georgia, serif; margin: 32px; color: #111; }
        h1 { margin: 0; font-size: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 13px; }
        th, td { border: 1px solid #333; padding: 6px; }
        th { background: #eee; }
        .sign { display: flex; justify-content: space-between; margin-top: 48px; }
        .line { border-top: 1px solid #111; width: 240px; padding-top: 6px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print"><button onclick="window.print()">Print</button></p>
    <h1>{{ $company }}</h1>
    <div>{{ $address }}</div>
    <h2>Daily Time Record</h2>
    <p>
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
                    <td>{{ optional($row->attendance_date)->format('M d, Y D') }}</td>
                    <td>{{ $row->time_in?->format('h:i A') }}</td>
                    <td>{{ $row->time_out?->format('h:i A') }}</td>
                    <td>{{ $row->totalHoursLabel() }}</td>
                    <td>{{ $row->late_minutes }}</td>
                    <td>{{ $row->undertime_minutes }}</td>
                    <td>{{ $row->overtimeHoursLabel() }}</td>
                    <td>{{ $row->status?->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="sign">
        <div class="line">Employee Signature</div>
        <div class="line">Authorized Official</div>
    </div>
</body>
</html>
