<!DOCTYPE html>
<html lang="en" class="dtr-print-root">
<head>
    <meta charset="utf-8">
    <title>Batch DTR — {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}</title>
    @include('reports.partials.print-theme', ['font' => 'serif'])
    @include('reports.partials.monthly-dtr-print-css')
</head>
<body class="dtr-print">
    <p class="no-print print-actions">
        <button type="button" onclick="window.bacsDtrPrint ? window.bacsDtrPrint() : window.print()">Print {{ count($sheets) }} DTR{{ count($sheets) === 1 ? '' : 's' }}</button>
        <a class="btn-link" href="{{ route('admin.dtr.monthly', array_filter(['month' => $month, 'year' => $year, 'department_id' => $departmentId ?? null])) }}">Back to monthly DTR</a>
    </p>

    <p class="no-print muted" style="margin: 0 0 16px;">
        {{ count($sheets) }} employee{{ count($sheets) === 1 ? '' : 's' }} ·
        {{ DateTime::createFromFormat('!m', $month)->format('F') }} {{ $year }}
        @if ($departmentName)
            · {{ $departmentName }}
        @endif
    </p>

    @foreach ($sheets as $index => $sheet)
        @include('reports.partials.monthly-dtr-sheet', [
            'employee' => $sheet['employee'],
            'rows' => $sheet['rows'],
            'year' => $year,
            'month' => $month,
            'company' => $company,
            'address' => $address,
            'pageBreak' => $index > 0,
        ])
    @endforeach
</body>
</html>