<!DOCTYPE html>
<html lang="en" class="dtr-print-root">
<head>
    <meta charset="utf-8">
    <title>Print DTR — {{ $employee->fullName() }}</title>
    @include('reports.partials.print-theme', ['font' => 'serif'])
    @include('reports.partials.monthly-dtr-print-css')
</head>
<body class="dtr-print">
    <p class="no-print print-actions">
        <button type="button" onclick="window.bacsDtrPrint ? window.bacsDtrPrint() : window.print()">Print this DTR</button>
        <a class="btn-link" href="{{ route('admin.dtr.monthly', ['employee_id' => $employee->id, 'month' => $month, 'year' => $year]) }}">Back to monthly DTR</a>
    </p>

    @include('reports.partials.monthly-dtr-sheet', [
        'employee' => $employee,
        'rows' => $rows,
        'year' => $year,
        'month' => $month,
        'company' => $company,
        'address' => $address,
    ])
</body>
</html>