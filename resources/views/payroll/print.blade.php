<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Payslip · {{ $row->employee_name }}</title>
    @include('reports.partials.print-theme')
    <style>@media print { .no-print { display: none; } }</style>
</head>
<body>
    <p class="no-print" style="margin-bottom: 1rem;">
        <button type="button" onclick="window.print()">Print</button>
        <a href="{{ $pdfUrl }}">Download PDF</a>
    </p>
    @include('payroll.partials.payslip-body')
</body>
</html>
