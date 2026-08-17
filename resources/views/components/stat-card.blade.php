@props(['label', 'value', 'hint' => null, 'tone' => 'slate'])

@php
    $tones = [
        'slate' => 'bg-slate-50 text-slate-700',
        'green' => 'bg-emerald-50 text-emerald-800',
        'orange' => 'bg-orange-50 text-orange-800',
        'red' => 'bg-red-50 text-red-800',
        'blue' => 'bg-blue-50 text-blue-800',
        'yellow' => 'bg-yellow-50 text-yellow-800',
        'teal' => 'bg-teal-50 text-teal-800',
    ];
@endphp

<div class="card p-5">
    <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</div>
    <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1 text-xs text-slate-500">{{ $hint }}</div>
    @endif
</div>
