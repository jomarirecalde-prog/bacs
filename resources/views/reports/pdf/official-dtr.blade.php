<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>DAILY TIME RECORD</title>
    <style>
        @page { margin: 36pt 18pt 36pt 18pt; size: A4 portrait; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #000;
            font-family: DejaVu Sans, Calibri, Arial, sans-serif;
            font-size: 10pt;
        }
        .title {
            text-align: center;
            font-size: 14pt;
            font-weight: 700;
            letter-spacing: 0.6px;
            margin: 18pt 0 10pt;
        }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
        .meta td { border: 0; padding: 1pt 0; font-size: 10pt; }
        .meta .right { text-align: right; }
        table.dtr { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.dtr th, table.dtr td {
            border: 1px solid #000;
            padding: 3pt 2pt;
            font-size: 10pt;
            height: 15.5pt;
            vertical-align: middle;
        }
        table.dtr th {
            font-size: 9pt;
            font-weight: 700;
            text-align: center;
        }
        table.dtr td { text-align: center; }
        table.dtr td.date { text-align: left; padding-left: 4pt; width: 13%; }
        table.dtr td.day { width: 13%; }
        .cert {
            font-style: italic;
            margin-top: 22pt;
            font-size: 10pt;
        }
        .signs { width: 100%; margin-top: 16pt; border-collapse: collapse; }
        .signs td { border: 0; font-size: 10pt; width: 50%; }
    </style>
</head>
<body>
    <div class="title">DAILY TIME RECORD</div>
    <table class="meta">
        <tr>
            <td>Employee Name: {{ $employeeName }}</td>
            <td class="right">Cut - Off : {{ $cutoff }}</td>
        </tr>
        <tr>
            <td>Department: {{ $department }}</td>
            <td></td>
        </tr>
    </table>
    <table class="dtr">
        <thead>
            <tr>
                <th>Date</th>
                <th>Day</th>
                <th>AM Time In</th>
                <th>AM Time Out</th>
                <th>PM Time In</th>
                <th>PM Time Out</th>
                <th>Overtime</th>
                <th>Total Hours</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td class="date">{{ $row['date'] }}</td>
                    <td class="day">{{ $row['day'] }}</td>
                    <td>{{ $row['am_in'] }}</td>
                    <td>{{ $row['am_out'] }}</td>
                    <td>{{ $row['pm_in'] }}</td>
                    <td>{{ $row['pm_out'] }}</td>
                    <td>{{ $row['overtime'] }}</td>
                    <td>{{ $row['total'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="cert">Certification: I certify that the above entries are true and correct.</p>
    <table class="signs">
        <tr>
            <td>Employee Signature: ____________________</td>
            <td>Approved by: ____________________</td>
        </tr>
    </table>
</body>
</html>
