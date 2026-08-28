<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Attendance Report' }}</title>
    @include('reports.partials.print-theme')
</head>
<body>
    <div class="doc-header">
        <h1>{{ $company ?? config('app.name') }}</h1>
        <div class="muted">{{ $address ?? '' }}</div>
    </div>
    <div class="doc-rule-gold"></div>

    <h2>{{ $title ?? 'Attendance Report' }} · Generated {{ now()->format('F j, Y g:i A') }} (Asia/Manila)</h2>

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
                    <td class="num">{{ $row->totalHoursLabel() }}</td>
                    <td class="num">{{ $row->late_minutes }}</td>
                    <td class="num">{{ $row->undertime_minutes }}</td>
                    <td class="num">{{ $row->overtime_minutes }}</td>
                    <td>{{ $row->status?->label() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
