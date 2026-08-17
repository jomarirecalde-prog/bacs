<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Attendance Report' }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0; }
        h2 { font-size: 13px; margin: 4px 0 12px; font-weight: normal; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f3f4f6; }
        .muted { color: #555; font-size: 10px; }
        .header { margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $company ?? config('app.name') }}</h1>
        <div class="muted">{{ $address ?? '' }}</div>
        <h2>{{ $title ?? 'Attendance Report' }} · Generated {{ now()->format('F j, Y g:i A') }} (Asia/Manila)</h2>
    </div>
    <table>
        <thead>
            <tr>
                <th>Employee</th><th>Dept</th><th>Date</th><th>In</th><th>Out</th><th>Hours</th><th>Late</th><th>UT</th><th>OT</th><th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->employee?->fullName() }}</td>
                    <td>{{ $row->employee?->department?->name }}</td>
                    <td>{{ optional($row->attendance_date)->format('Y-m-d') }}</td>
                    <td>{{ $row->time_in?->format('H:i') }}</td>
                    <td>{{ $row->time_out?->format('H:i') }}</td>
                    <td>{{ $row->totalHoursLabel() }}</td>
                    <td>{{ $row->late_minutes }}</td>
                    <td>{{ $row->undertime_minutes }}</td>
                    <td>{{ $row->overtime_minutes }}</td>
                    <td>{{ $row->status?->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
