<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $request->reference_number }} — Official Business</title>
  @include('reports.partials.print-theme')
    <style>
        body { font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 11px; color: #111; margin: 20px 28px; }
        .ob-letterhead { display: block; width: 100%; height: auto; margin: 0 0 8px; }
        .ob-title { text-align: center; font-weight: 700; font-size: 14px; letter-spacing: 1px; margin: 8px 0 14px; text-decoration: underline; }
        table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.meta td { padding: 4px 6px; vertical-align: top; border: 1px solid #333; }
        table.meta .label { font-weight: 700; width: 22%; background: #f5f5f5; }
        table.participants { width: 100%; border-collapse: collapse; margin: 12px 0 16px; }
        table.participants th, table.participants td { border: 1px solid #333; padding: 5px 8px; text-align: left; }
        table.participants th { background: #f0f0f0; font-size: 10px; }
        .sig { width: 100%; margin-top: 28px; border-collapse: collapse; }
        .sig td { width: 50%; vertical-align: bottom; text-align: center; padding-top: 12px; }
        .sig-line {
            border-top: 1px solid #111;
            margin: 44px auto 6px;
            width: 80%;
            padding-top: 10px;
            min-height: 16px;
            text-align: center;
        }
        .sig-approved {
            display: inline-block;
            font-size: 9px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: #047857;
            border: 2px solid #047857;
            border-radius: 999px;
            padding: 3px 10px;
            white-space: nowrap;
            line-height: 1.3;
        }
        .sig-caption { font-size: 10px; line-height: 1.4; }
    </style>
</head>
<body>
    @if ($letterheadSrc ?? null)
        <img src="{{ $letterheadSrc }}" alt="" class="ob-letterhead">
    @endif

    <div class="ob-title">OFFICIAL BUSINESS</div>

    <table class="meta">
        <tr>
            <td class="label">Control No.</td>
            <td>{{ $request->reference_number }}</td>
            <td class="label">Date Filed</td>
            <td>{{ $filedDate }}</td>
        </tr>
        <tr>
            <td class="label">Name of Requester</td>
            <td colspan="3">{{ $request->employee?->fullName() ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Position / Department</td>
            <td colspan="3">{{ trim(($request->employee?->position ?? '').' / '.($request->employee?->department?->name ?? '')) ?: '—' }}</td>
        </tr>
        <tr>
            <td class="label">Official Station</td>
            <td>{{ $request->official_station ?? '—' }}</td>
            <td class="label">Departure Time</td>
            <td>{{ filled($request->departure_time) ? substr((string) $request->departure_time, 0, 5) : '—' }}</td>
        </tr>
        <tr>
            <td class="label">Destination (From – To)</td>
            <td colspan="3">{{ $request->destinationLabel() }}</td>
        </tr>
        <tr>
            <td class="label">Inclusive Dates</td>
            <td colspan="3">{{ $request->dateRangeLabel() }}</td>
        </tr>
        <tr>
            <td class="label">Purpose</td>
            <td colspan="3">{{ $request->purpose }}</td>
        </tr>
        @if ($request->purpose_particular)
            <tr>
                <td class="label">Particulars</td>
                <td colspan="3">{{ $request->purpose_particular }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Transportation</td>
            <td colspan="3">{{ $request->transportationLabel() }}@if($request->vehicle_name || $request->plate_number) — {{ trim(($request->vehicle_name ?? '').' '.($request->plate_number ?? '')) }}@endif</td>
        </tr>
        <tr>
            <td class="label">Accommodation</td>
            <td colspan="3">{{ $request->accommodation_type ?? '—' }}@if($request->accommodation_particular) — {{ $request->accommodation_particular }}@endif</td>
        </tr>
    </table>

    <div style="font-weight:700; margin-bottom:4px;">Personnel / Participants</div>
    <table class="participants">
        <thead>
            <tr>
                <th style="width:6%;">No.</th>
                <th>Name</th>
                <th style="width:28%;">Position / Remarks</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($participantLines as $index => $line)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $line?->participant_name ?? ' ' }}</td>
                    <td>{{ $line?->employee?->position ?? ($line ? ' ' : ' ') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="sig">
        <tr>
            <td>
                <div class="sig-caption font-semibold">Requested By:</div>
                <div class="sig-line"></div>
                <div class="sig-caption"><strong>{{ $requestedBy?->fullName() ?? ' ' }}</strong></div>
                <div class="sig-caption">{{ $requestedBy?->position ?? 'Signature over Printed Name' }}</div>
            </td>
            <td>
                <div class="sig-caption font-semibold">Approved By:</div>
                <div class="sig-line">
                    @include('reports.partials.approval-emblem', [
                        'show' => $showApprovedEmblem ?? false,
                        'date' => $approvedEmblemDate ?? null,
                    ])
                </div>
                <div class="sig-caption"><strong>{{ $approvedBy?->approver_name ?? ' ' }}</strong></div>
                <div class="sig-caption">{{ $approvedBy?->approver_position ?? 'Signature over Printed Name' }}</div>
            </td>
        </tr>
    </table>

    @if ($checkedBy)
        <p style="margin-top:12px; font-size:10px; text-align:center;">Checked / endorsed by {{ $checkedBy->approver_name }} ({{ $checkedBy->approver_position ?? '—' }})</p>
    @endif
</body>
</html>
