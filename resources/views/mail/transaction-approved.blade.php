@extends('mail.layout')

@section('email_title', 'Transaction Approved')

@section('content')
    <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#047857;">Approval Notification</p>
    <h2 style="margin:0 0 16px;font-size:24px;line-height:1.3;font-weight:700;color:#111827;">{{ $payload['title'] }}</h2>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        Hello {{ $payload['greeting_name'] }},
    </p>
    <p style="margin:0 0 16px;font-size:15px;line-height:1.65;color:#374151;">
        {{ $payload['intro'] }}
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
            ['label' => 'Reference No.', 'value' => $payload['reference_number']],
            ['label' => 'Transaction Type', 'value' => $payload['transaction_type']],
            ['label' => 'Description', 'value' => $payload['description']],
            ['label' => 'Approval Date & Time', 'value' => $payload['action_at']],
            ['label' => 'Approver', 'value' => $payload['actor_name']],
            ['label' => 'Current Status', 'value' => $payload['status_label']],
        ],
    ])
@endsection

@section('cta')
    @include('mail.partials.cta-button', [
        'label' => $payload['cta_label'],
        'url' => $payload['cta_url'],
    ])
@endsection
