<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payroll Register — {{ $period->period_name }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #111; }
        h1 { font-size: 14px; margin: 0 0 4px; }
        .meta { color: #444; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 3px 4px; }
        th { background: #eee; text-align: left; }
        .num { text-align: right; }
        tfoot td { font-weight: bold; background: #f5f5f5; }
    </style>
</head>
<body>
    <h1>{{ $company }}</h1>
    <div class="meta">Payroll register — {{ $period->period_name }} · {{ $period->start_date->format('M j, Y') }} – {{ $period->end_date->format('M j, Y') }}</div>
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Designation</th>
                <th class="num">Basic</th>
                <th class="num">Total Basic</th>
                <th class="num">OT</th>
                <th class="num">Holiday</th>
                <th class="num">Gross Wage</th>
                <th class="num">Gross Comp.</th>
                <th class="num">Deductions</th>
                <th class="num">Net Pay</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row->employee_name }}<br><small>{{ $row->employee_number }}</small></td>
                    <td>{{ $row->designation_name ?? '—' }}</td>
                    <td class="num">{{ number_format($row->basic_pay, 2) }}</td>
                    <td class="num">{{ number_format($row->total_basic_pay, 2) }}</td>
                    <td class="num">{{ number_format($row->overtime_pay, 2) }}</td>
                    <td class="num">{{ number_format($row->holiday_pay + $row->premium_pay, 2) }}</td>
                    <td class="num">{{ number_format($row->gross_wage, 2) }}</td>
                    <td class="num">{{ number_format($row->gross_compensation, 2) }}</td>
                    <td class="num">{{ number_format($row->total_deductions, 2) }}</td>
                    <td class="num">{{ number_format($row->net_pay, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="2">Totals ({{ $rows->count() }})</td>
                    <td class="num">{{ number_format($totals['basic_pay'], 2) }}</td>
                    <td class="num">{{ number_format($totals['total_basic_pay'], 2) }}</td>
                    <td class="num">{{ number_format($totals['overtime_pay'], 2) }}</td>
                    <td class="num">{{ number_format($totals['holiday_pay'] + $totals['premium_pay'], 2) }}</td>
                    <td class="num">{{ number_format($totals['gross_wage'], 2) }}</td>
                    <td class="num">{{ number_format($totals['gross_compensation'], 2) }}</td>
                    <td class="num">{{ number_format($totals['total_deductions'], 2) }}</td>
                    <td class="num">{{ number_format($totals['net_pay'], 2) }}</td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
