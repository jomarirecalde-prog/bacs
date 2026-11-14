@extends('mail.layout')

@section('email_title', 'Reset Your Password')

@section('content')
    <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#047857;">Account Security</p>
    <h2 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:#111827;">Password reset requested</h2>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        Hello {{ $payload['greeting_name'] }},
    </p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        We received a request to reset the password for username <strong>{{ $payload['username'] }}</strong>.
        Use the secure button below to choose a new password. This link expires in about one hour.
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
            ['label' => 'Username', 'value' => $payload['username']],
            ['label' => 'Requested', 'value' => $payload['requested_at']],
        ],
    ])

    <p style="margin:16px 0 0;font-size:13px;line-height:1.6;color:#6b7280;">
        If you did not request this change, you can ignore this message. Your password will stay the same until you use the link above.
    </p>
@endsection

@section('cta')
    @include('mail.partials.cta-button', [
        'label' => $payload['cta_label'],
        'url' => $payload['cta_url'],
    ])
@endsection
