<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $order->travel_order_number }} — Travel Order</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 28px; }
        .header { text-align: center; border-bottom: 2px solid #0f5132; padding-bottom: 12px; margin-bottom: 18px; }
        .header img { height: 64px; }
        .company { font-size: 10px; line-height: 1.4; margin-top: 6px; }
        h1 { text-align: center; letter-spacing: 2px; font-size: 18px; margin: 16px 0; }
        table.meta { width: 100%; margin-bottom: 14px; }
        table.meta td { padding: 4px 0; vertical-align: top; }
        .label { font-weight: bold; width: 140px; }
        .personnel { margin: 10px 0 16px; }
        .personnel-item { padding: 6px 0; border-bottom: 1px dotted #ccc; }
        .auth { margin-top: 24px; font-style: italic; text-align: center; }
        .signatures { margin-top: 36px; width: 100%; }
        .signatures td { width: 50%; vertical-align: top; padding-top: 40px; }
        .line { border-top: 1px solid #111; width: 80%; margin-top: 48px; }
    </style>
</head>
<body>
    <div class="header">
        @if ($logoSrc)<img src="{{ $logoSrc }}" alt="BACS Logo">@endif
        <div class="company"><strong>BACS CONSTRUCTION AND DEVELOPMENT CORPORATION</strong><br>Official Travel Order</div>
    </div>

    <h1>TRAVEL ORDER</h1>

    <table class="meta">
        <tr><td class="label">Document No.</td><td>{{ $order->travel_order_number }}</td><td class="label">Date</td><td>{{ $issuedDate }}</td></tr>
    </table>

    <div class="label">NAME (Involved Personnel)</div>
    <div class="personnel">
        @forelse ($travelers as $traveler)
            <div class="personnel-item">
                <strong>{{ $traveler->fullName() }}</strong><br>
                {{ $traveler->position ?? '—' }}
            </div>
        @empty
            <div class="personnel-item">—</div>
        @endforelse
    </div>

    <table class="meta">
        <tr><td class="label">OFFICIAL STATION</td><td colspan="3">{{ $order->official_station ?? '—' }}</td></tr>
        <tr><td class="label">NUMBER OF BH</td><td>{{ $order->number_of_bh ?? '—' }}</td><td class="label">DATE COVERED</td><td>{{ $order->dateRangeLabel() }}</td></tr>
        <tr><td class="label">DESTINATION/S</td><td colspan="3">{{ $order->destination ?? '—' }}</td></tr>
        <tr><td class="label">PURPOSE</td><td colspan="3">{{ $order->purpose }}</td></tr>
        <tr><td class="label">EQUIPMENT</td><td>{{ $order->equipment ?? '—' }}</td><td class="label">PROJECT NAME</td><td>{{ $order->project_name ?? '—' }}</td></tr>
        <tr><td class="label">CLIENT / COMPANY</td><td colspan="3">{{ $order->client_company ?? '—' }}</td></tr>
        <tr><td class="label">VIA / TRANSPORTATION</td><td colspan="3">{{ $order->transportationLabel() }} @if($order->vehicle_type || $order->plate_number) — {{ trim($order->vehicle_type.' '.$order->plate_number) }} @endif</td></tr>
    </table>

    <p class="auth">You are hereby authorized to travel for the foregoing purpose.</p>

    <table class="signatures">
        <tr>
            <td>
                <div class="label">Checked By:</div>
                <div class="line"></div>
                <div>{{ $checkedBy?->approver_name ?? 'EHS Head' }}</div>
                <div>{{ $checkedBy?->approver_position ?? 'Signature / Position' }}</div>
            </td>
            <td>
                <div class="label">Approved By:</div>
                <div class="line"></div>
                <div>{{ $approvedBy?->approver_name ?? 'Admin Manager' }}</div>
                <div>{{ $approvedBy?->approver_position ?? 'Signature / Position' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
