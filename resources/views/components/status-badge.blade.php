@props(['status'])

@php
    $value = $status instanceof \App\Enums\AttendanceStatus
        ? $status
        : (is_string($status) ? \App\Enums\AttendanceStatus::tryFrom($status) : null);
    $label = $value?->label() ?? (is_string($status) && $status !== '' ? $status : '—');
    $color = $value?->color() ?? 'slate';
    $classes = [
        'green' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'orange' => 'bg-orange-50 text-orange-800 border-orange-200',
        'red' => 'bg-red-50 text-red-800 border-red-200',
        'yellow' => 'bg-yellow-50 text-yellow-800 border-yellow-200',
        'blue' => 'bg-blue-50 text-blue-800 border-blue-200',
        'purple' => 'bg-purple-50 text-purple-800 border-purple-200',
        'slate' => 'bg-slate-100 text-slate-700 border-slate-200',
        'indigo' => 'bg-indigo-50 text-indigo-800 border-indigo-200',
        'amber' => 'bg-amber-50 text-amber-800 border-amber-200',
        'teal' => 'bg-teal-50 text-teal-800 border-teal-200',
    ][$color] ?? 'bg-slate-100 text-slate-700 border-slate-200';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold $classes"]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</span>
