<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Manual Salary Computation #{{ $computation->id }}</title>
    <style>
        body { font-family: system-ui, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; }
        .right { text-align: right; }
        .net { font-size: 16px; font-weight: 700; margin-top: 16px; }
        @media print { body { margin: 12px; } }
    </style>
</head>
<body>
    <h1>Manual Salary Computation</h1>
    <p class="muted">BACS Construction and Development Corporation · Payroll</p>

    <table>
        <tr><th>Employee</th><td>{{ $employee->fullName() }} ({{ $employee->employee_number }})</td></tr>
        <tr><th>Department</th><td>{{ $employee->department?->name ?? '—' }}</td></tr>
        <tr><th>Coverage</th><td>{{ $computation->coverage_start->format('M j, Y') }} – {{ $computation->coverage_end->format('M j, Y') }}</td></tr>
        <tr><th>Mode</th><td>{{ $computation->computation_mode?->label() }}</td></tr>
        <tr><th>Status</th><td>{{ $computation->status?->label() }}</td></tr>
        <tr><th>Prepared by</th><td>{{ $computation->preparedByUser?->name ?? '—' }} @if($computation->prepared_at) · {{ $computation->prepared_at->format('M j, Y g:i A') }} @endif</td></tr>
    </table>

    <table>
        <thead><tr><th colspan="2">Earnings</th></tr></thead>
        <tbody>
            @foreach ($computation->lines->where('direction', 'earning') as $line)
                <tr><td>{{ $line->label }}</td><td class="right">₱{{ number_format($line->amount, 2) }}</td></tr>
            @endforeach
        </tbody>
        <thead><tr><th colspan="2">Deductions</th></tr></thead>
        <tbody>
            @foreach ($computation->lines->where('direction', 'deduction') as $line)
                <tr><td>{{ $line->label }}</td><td class="right">−₱{{ number_format($line->amount, 2) }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <p class="net">Net Pay: ₱{{ number_format($computation->net_pay, 2) }}</p>
    @if ($computation->remarks)
        <p><strong>Remarks:</strong> {{ $computation->remarks }}</p>
    @endif

    <script>window.onload = function () { window.print(); };</script>
</body>
</html>
