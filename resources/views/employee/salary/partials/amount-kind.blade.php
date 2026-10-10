@php
    $labels = [
        'released' => ['Released salary', 'badge-brand', 'Confirmed net pay for this finalized period.'],
        'approved' => ['Approved payroll', 'badge-info', 'Payroll is approved; release is pending.'],
        'estimate' => ['Estimated salary', 'badge-warn', 'Subject to change until payroll is finalized.'],
        'none' => ['Not computed', 'badge-neutral', 'Payroll has not been computed for this period yet.'],
    ];
    [$title, $badgeClass, $hint] = $labels[$amountKind] ?? $labels['none'];
@endphp
<span class="{{ $badgeClass }}">{{ $title }}</span>
@if ($showHint ?? false)
    <p class="mt-1 text-xs text-muted">{{ $hint }}</p>
@endif
