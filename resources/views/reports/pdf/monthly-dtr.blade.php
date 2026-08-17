<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Monthly DTR - {{ $employee->fullName() }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 4px 6px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #555; }
        .sign { margin-top: 36px; display: table; width: 100%; }
        .sign div { display: table-cell; width: 50%; }
        .line { margin-top: 40px; border-top: 1px solid #111; width: 220px; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>{{ $company ?? config('app.name') }}</h1>
    <div class="muted">{{ $address ?? '' }}</div>
    <p><strong>DAILY TIME RECORD</strong><br>
        Employee: {{ $employee->fullName() }} ({{ $employee->employee_number }})<br>
        Department: {{ $employee->department?->name }} · Position: {{ $employee->position }}<br>
        Period: {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}<br>
        Generated: {{ now()->format('F j, Y g:i A') }} (Asia/Manila)
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
                    <td>{{ optional($row->attendance_date)->format('M d, Y D') }}</td>
                    <td>{{ $row->time_in?->format('h:i A') ?? '' }}</td>
                    <td>{{ $row->time_out?->format('h:i A') ?? '' }}</td>
                    <td>{{ $row->totalHoursLabel() }}</td>
                    <td>{{ $row->late_minutes }}</td>
                    <td>{{ $row->undertime_minutes }}</td>
                    <td>{{ $row->overtimeHoursLabel() }}</td>
                    <td>{{ $row->status?->label() }}</td>
                    <td>{{ $row->remarks }}{{ $row->is_edited ? ' (edited)' : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <div class="sign">
        <div>
            <div class="line">Employee Signature</div>
        </div>
        <div>
            <div class="line">Supervisor / Admin Signature</div>
        </div>
    </div>
    <p class="muted">I certify that the above is a true and correct report of the hours of work performed.</p>
</body>
</html>
