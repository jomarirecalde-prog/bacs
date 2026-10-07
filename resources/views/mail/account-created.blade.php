@extends('mail.layout')

@section('email_title', 'Account Successfully Created')

@section('content')
    <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#047857;">Account Notification</p>
    <h2 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:#111827;">Welcome to BACS</h2>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        Hello {{ $payload['greeting_name'] }},
    </p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        Your employee account has been successfully created in the BACS Management System. Use the secure button below to set your password. Do not share this link.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        <tr>
            <td style="padding:6px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;{{ \App\Support\EmailBranding::badgeStyle($payload['status_badge']) }}">
                {{ $payload['status_label'] }}
            </td>
        </tr>
    </table>

    @include('mail.partials.detail-table', [
        'rows' => [
            ['label' => 'Employee Name', 'value' => $payload['employee_name']],
            ['label' => 'Employee Number', 'value' => $payload['employee_number']],
            ['label' => 'Username', 'value' => $payload['username']],
            ['label' => 'Registered Email', 'value' => $payload['email']],
            ['label' => 'Registration Date', 'value' => $payload['registered_at']],
            ['label' => 'Account Status', 'value' => $payload['account_status']],
        ],
    ])

    <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#6b7280;">
        Keep these credentials private. If you did not expect this account, contact your administrator immediately.
    </p>
@endsection

@section('cta')
    @include('mail.partials.cta-button', [
        'label' => $payload['cta_label'],
        'url' => $payload['cta_url'],
    ])
@endsection
