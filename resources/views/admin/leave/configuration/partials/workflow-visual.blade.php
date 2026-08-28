@php
    $stageFlow = [
        ['key' => 'employee', 'label' => 'EMPLOYEE', 'hint' => 'Submits leave application'],
        ['key' => 'immediate_supervisor', 'label' => 'IMMEDIATE SUPERVISOR / SUPERIOR', 'hint' => 'Parallel approval when multiple assigned'],
        ['key' => 'department_head', 'label' => 'DEPARTMENT HEAD', 'hint' => 'Department-specific approver'],
        ['key' => 'administrative_head', 'label' => 'ADMINISTRATIVE HEAD', 'hint' => 'Administrative review'],
        ['key' => 'ceo', 'label' => 'CEO', 'hint' => 'FINAL APPROVAL — system designated'],
    ];
@endphp

<div class="rounded-2xl border border-line bg-canvas/60 p-4">
    <div class="space-y-0">
        @foreach ($stageFlow as $index => $step)
            <div class="flex flex-col items-center">
                <div class="w-full max-w-md rounded-xl border border-line bg-white px-4 py-3 text-center shadow-soft">
                    <div class="text-xs font-bold tracking-wide text-brand-800">{{ $step['label'] }}</div>
                    @if ($step['key'] === 'ceo')
                        <div class="mt-1 text-sm font-semibold text-ink">{{ $ceoLabel }}</div>
                        <div class="mt-1 text-[11px] font-bold uppercase tracking-wider text-gold-700">Final Approval</div>
                    @else
                        <div class="mt-1 text-xs text-muted">{{ $step['hint'] }}</div>
                    @endif
                </div>
                @if ($index < count($stageFlow) - 1)
                    <div class="py-2 text-muted" aria-hidden="true">▼</div>
                @endif
            </div>
        @endforeach
    </div>
</div>
