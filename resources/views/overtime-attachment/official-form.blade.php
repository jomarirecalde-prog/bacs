<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $report->report_no }} — OT Attachment Report</title>
    @include('reports.partials.print-theme')
    <style>
        body { font-family: "DejaVu Serif", "Times New Roman", serif; font-size: 11px; color: #111; }
        .ot-letterhead { display: block; width: 100%; height: auto; margin: 0 0 6px; }
        .ot-title-wrap { width: 100%; margin: 0 0 6px; border-collapse: collapse; }
        .ot-title-wrap td { border: 0; text-align: center; font-weight: 700; font-size: 13px; padding: 2px 0 6px; }
        .ot-meta { width: 100%; border-collapse: collapse; margin: 0 0 14px; }
        .ot-meta td { border: 0; padding: 2px 4px 4px; vertical-align: bottom; font-size: 11px; }
        .ot-meta__label { width: 11%; white-space: nowrap; }
        .ot-meta__value { border-bottom: 1px solid #111; font-weight: 600; }
        .ot-meta__pair { width: 38%; }
        .sig td { width: 50%; padding-top: 36px; vertical-align: bottom; text-align: center; }
        .sig-line {
            border-top: 1px solid #10201b;
            margin: 48px auto 4px;
            width: 85%;
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
        .sig-caption {
            margin: 4px 0 0;
            font-size: 11px;
            line-height: 1.45;
        }
    </style>
</head>
<body>
    @if ($letterheadSrc ?? null)
        <img src="{{ $letterheadSrc }}" alt="" class="ot-letterhead">
    @endif

    <table class="ot-title-wrap">
        <tr>
            <td>Overtime Attachment Report</td>
        </tr>
    </table>

    <table class="ot-meta">
        <tr>
            <td class="ot-meta__label">Name:</td>
            <td class="ot-meta__value ot-meta__pair">{{ $report->employee?->fullName() }}</td>
            <td class="ot-meta__label" style="padding-left: 12px;">Date coverage:</td>
            <td class="ot-meta__value">{{ $report->coverageLabel() }}</td>
        </tr>
        <tr>
            <td class="ot-meta__label">Position:</td>
            <td class="ot-meta__value ot-meta__pair">{{ $report->employee?->position ?? ' ' }}</td>
            <td class="ot-meta__label" style="padding-left: 12px;">Date submitted:</td>
            <td class="ot-meta__value">{{ $submittedDate }}</td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width:4%;">No.</th>
                <th>Activities</th>
                <th style="width:12%;">Date</th>
                <th style="width:18%;">Time From / To</th>
                <th style="width:12%;">Duration</th>
                <th style="width:14%;">Status / Remark</th>
                <th style="width:14%;">Reviewed / Vouched By</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($report->activities as $index => $activity)
                <tr>
                    <td class="tabular-nums text-center">{{ $index + 1 }}</td>
                    <td>{{ $activity->activity_description }}</td>
                    <td>{{ $activity->activity_date?->format('m/d/Y') }}</td>
                    <td>{{ $activity->timeRangeLabel() }}</td>
                    <td>{{ $activity->durationLabel() }}</td>
                    <td>{{ $activity->status_remark ?? '—' }}</td>
                    <td>{{ $activity->reviewed_vouched_by ?? '—' }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="4" class="text-right"><strong>Total</strong></td>
                <td colspan="3"><strong>{{ $report->durationLabel() }}</strong></td>
            </tr>
        </tbody>
    </table>

    <table class="sig" style="margin-top: 28px;">
        <tr>
            @php
                $reviewedName = $endorsedBy?->approver_name;
                $approvedName = $report->approved_by ?: ($approvedAssignment?->approver_name);
            @endphp
            <td>
                <div class="sig-line">
                    @include('reports.partials.approval-emblem', [
                        'show' => $showReviewedEmblem ?? false,
                        'date' => $reviewedEmblemDate ?? null,
                    ])
                </div>
                <p class="sig-caption">
                    <strong>Reviewed / Vouched by</strong>@if ($reviewedName) · {{ $reviewedName }}@endif
                </p>
            </td>
            <td>
                <div class="sig-line">
                    @include('reports.partials.approval-emblem', [
                        'show' => $showApprovedEmblem ?? false,
                        'date' => $approvedEmblemDate ?? null,
                    ])
                </div>
                <p class="sig-caption">
                    <strong>Approved by</strong>@if ($approvedName) · {{ $approvedName }}@endif
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
