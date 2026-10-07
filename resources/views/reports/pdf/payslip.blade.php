<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $row->employee_name }}</title>
    @include('reports.partials.print-theme')
</head>
<body>
    @include('payroll.partials.payslip-body')

    <table>
        <thead><tr><th colspan="2">Earnings</th></tr></thead>
        <tbody>
            <tr><td>Basic Pay</td><td class="num">₱{{ number_format($row->basic_pay, 2) }}</td></tr>
            @if ($row->absence_deduction + $row->late_deduction + $row->undertime_deduction > 0)
                <tr><td>Less: Absent / Late / Undertime</td><td class="num">−₱{{ number_format($row->absence_deduction + $row->late_deduction + $row->undertime_deduction, 2) }}</td></tr>
            @endif
            <tr><td><strong>Total Basic Pay</strong></td><td class="num"><strong>₱{{ number_format($row->total_basic_pay, 2) }}</strong></td></tr>
            @if ($row->overtime_pay > 0)
                <tr><td>Overtime</td><td class="num">₱{{ number_format($row->overtime_pay, 2) }}</td></tr>
            @endif
            @if ($row->holiday_pay > 0)
                <tr><td>Holiday Pay</td><td class="num">₱{{ number_format($row->holiday_pay, 2) }}</td></tr>
            @endif
            @if ($row->premium_pay > 0)
                <tr><td>Premium Pay</td><td class="num">₱{{ number_format($row->premium_pay, 2) }}</td></tr>
            @endif
            <tr><td><strong>Gross Wage</strong></td><td class="num"><strong>₱{{ number_format($row->gross_wage, 2) }}</strong></td></tr>
            @if ($row->de_minimis > 0)
                <tr><td>De Minimis</td><td class="num">₱{{ number_format($row->de_minimis, 2) }}</td></tr>
            @endif
            @if ($row->other_earnings > 0)
                <tr><td>Other Earnings / Adjustments</td><td class="num">₱{{ number_format($row->other_earnings, 2) }}</td></tr>
            @endif
            <tr><td><strong>Gross Compensation</strong></td><td class="num"><strong>₱{{ number_format($row->gross_compensation, 2) }}</strong></td></tr>
        </tbody>
    </table>

    <table style="margin-top: 16px;">
        <thead><tr><th colspan="2">Deductions</th></tr></thead>
        <tbody>
            @forelse ($row->deductions->whereNotIn('code', ['absence', 'late', 'undertime']) as $deduction)
                <tr><td>{{ $deduction->label }}</td><td class="num">₱{{ number_format($deduction->amount, 2) }}</td></tr>
            @empty
                <tr><td colspan="2" class="muted">No statutory or loan deductions</td></tr>
            @endforelse
        </tbody>
    </table>

    <table style="margin-top: 16px;">
        <tbody>
            <tr>
                <td><strong>NET PAY</strong></td>
                <td class="num" style="font-size: 14px;"><strong>₱{{ number_format($row->net_pay, 2) }}</strong></td>
            </tr>
        </tbody>
    </table>

    <p class="muted" style="margin-top: 24px; font-size: 10px;">This payslip is system-generated. For questions, contact HR / Finance.</p>
</body>
</html>
