@extends('mail.layout')

@section('email_title', 'Payslip Available')

@section('content')
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">Hello {{ $row->employee_name }},</p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        Your payslip for <strong>{{ $period->period_name }}</strong>
        ({{ $period->start_date->format('M j') }} – {{ $period->end_date->format('M j, Y') }}) is now available in BACS.
    </p>
    <p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#111827;">Net pay: ₱{{ number_format($row->net_pay, 2) }}</p>
    <p style="margin:0 0 16px;"><a href="{{ $viewUrl }}" style="display:inline-block;padding:10px 18px;background:#047857;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;">View payslip</a></p>
    <p style="margin:0;font-size:13px;color:#6b7280;">If you did not expect this message, contact your HR administrator.</p>
@endsection
