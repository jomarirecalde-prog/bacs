@php
    $row = $payrollEmployee;
    $allowances = (float) $row->de_minimis + (float) $row->other_earnings + (float) $row->holiday_pay + (float) $row->premium_pay;
    $attendanceDeductions = (float) $row->absence_deduction + (float) $row->late_deduction + (float) $row->undertime_deduction;
    $otherDeductions = max(0, (float) $row->total_deductions - $attendanceDeductions);
@endphp

<div class="grid gap-6 lg:grid-cols-2">
    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Earnings</h2></div>
        <dl class="divide-y divide-line p-5 text-sm">
            @foreach ([
                ['Total Basic Pay', $row->total_basic_pay],
                ['Allowances & benefits', $allowances > 0 ? $allowances : null],
                ['Overtime Pay', $row->overtime_pay > 0 ? $row->overtime_pay : null],
            ] as [$label, $amount])
                @if ($amount !== null && $amount != 0)
                    <div class="flex justify-between gap-4 py-2">
                        <dt class="text-muted">{{ $label }}</dt>
                        <dd class="tabular-nums font-semibold text-ink">₱{{ number_format($amount, 2) }}</dd>
                    </div>
                @endif
            @endforeach
            <div class="flex justify-between gap-4 py-2 font-semibold">
                <dt>Gross Salary</dt>
                <dd class="tabular-nums text-brand-700">₱{{ number_format($row->gross_compensation, 2) }}</dd>
            </div>
        </dl>
        @if ($row->earnings->isNotEmpty())
            <div class="border-t border-line px-5 pb-5 pt-3">
                <div class="text-xs font-bold uppercase tracking-wide text-muted">Itemized earnings</div>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @foreach ($row->earnings as $line)
                        <li class="flex justify-between py-2">
                            <span>{{ $line->label }}</span>
                            <span class="tabular-nums font-semibold">₱{{ number_format($line->amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>

    <div class="card overflow-hidden">
        <div class="card-header"><h2 class="card-title">Deductions</h2></div>
        <dl class="divide-y divide-line p-5 text-sm">
            @if ($attendanceDeductions > 0)
                @foreach ([
                    ['Absences', $row->absence_deduction],
                    ['Late penalties', $row->late_deduction],
                    ['Undertime', $row->undertime_deduction],
                ] as [$label, $amount])
                    @if ($amount > 0)
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-muted">{{ $label }}</dt>
                            <dd class="tabular-nums font-semibold text-critical-700">−₱{{ number_format($amount, 2) }}</dd>
                        </div>
                    @endif
                @endforeach
            @endif
            @if ($otherDeductions > 0)
                <div class="flex justify-between gap-4 py-2">
                    <dt class="text-muted">Loans, statutory & other</dt>
                    <dd class="tabular-nums font-semibold text-critical-700">−₱{{ number_format($otherDeductions, 2) }}</dd>
                </div>
            @endif
            <div class="flex justify-between gap-4 py-2 font-semibold">
                <dt>Total Deductions</dt>
                <dd class="tabular-nums text-critical-700">−₱{{ number_format($row->total_deductions, 2) }}</dd>
            </div>
        </dl>
        @if ($row->deductions->isNotEmpty())
            <div class="border-t border-line px-5 pb-5 pt-3">
                <div class="text-xs font-bold uppercase tracking-wide text-muted">Itemized deductions</div>
                <ul class="mt-2 divide-y divide-line text-sm">
                    @foreach ($row->deductions as $line)
                        <li class="flex justify-between py-2">
                            <span>{{ $line->label }}</span>
                            <span class="tabular-nums font-semibold text-critical-700">−₱{{ number_format($line->amount, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
