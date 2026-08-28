<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $application->application_number ?? 'LEAVE FORM' }}</title>
    @include('leave.partials.official-form-css')
    <style>
        @page { margin: 18px 20px; size: A4 portrait; }
        body { margin: 0; }
    </style>
</head>
<body>
    @include('leave.partials.official-form')
</body>
</html>
