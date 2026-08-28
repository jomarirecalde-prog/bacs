<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $application->application_number ?? 'LEAVE FORM' }}</title>
    @include('leave.partials.official-form-css')
    <style>
        @page { margin: 12mm; size: A4 portrait; }
        body { margin: 0; background: #fff; }
        .no-print { margin: 12px 16px 16px; font-family: sans-serif; }
        .no-print button, .no-print a {
            display: inline-block;
            background: #047857;
            color: #fff;
            border: 0;
            border-radius: 6px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            margin-right: 8px;
        }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" onclick="window.print()">Print official leave form</button>
        <a href="{{ url()->previous() }}">Back</a>
    </div>
    @include('leave.partials.official-form')
</body>
</html>
