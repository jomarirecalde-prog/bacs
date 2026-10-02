@extends('mail.layout')

@section('email_title', 'Account Access Updated')

@section('content')
    <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#047857;">Account Notification</p>
    <h2 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:#111827;">Your BACS login was updated</h2>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        Hello {{ $payload['greeting_name'] }},
    </p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        An administrator updated your account access in the BACS Management System. Sign in with your employee number, username, or registered email address, together with the password shown below if it was reset.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 8px;">
        <tr>
            <td style="padding:6px 12px;border-radius:999px;font-size:11px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;{{ \App\Support\EmailBranding::badgeStyle($payload['status_badge']) }}">
                {{ $payload['status_label'] }}
            </td>
        </tr>
    </table>

    @include('mail.partials.detail-table', [
        'rows' => array_filter([
            ['label' => 'Employee Name', 'value' => $payload['employee_name']],
            ['label' => 'Employee Number', 'value' => $payload['employee_number']],
            ['label' => 'Username', 'value' => $payload['username']],
            ['label' => 'Password', 'value' => $payload['password_display']],
            ['label' => 'Registered Email', 'value' => $payload['email']],
            ['label' => 'Updated At', 'value' => $payload['updated_at']],
            ['label' => 'Role', 'value' => $payload['role']],
            ['label' => 'Account Status', 'value' => $payload['account_status']],
        ], fn (array $row) => filled($row['value'] ?? null)),
    ])

    <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#6b7280;">
        Keep these credentials private. If you did not expect this change, contact your administrator immediately.
    </p>
@endsection

@section('cta')
    @include('mail.partials.cta-button', [
        'label' => $payload['cta_label'],
        'url' => $payload['cta_url'],
    ])
@endsection
