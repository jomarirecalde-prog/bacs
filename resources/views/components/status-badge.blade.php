@props(['status'])

@php
    $value = $status instanceof \App\Enums\AttendanceStatus
        ? $status
        : (is_string($status) ? \App\Enums\AttendanceStatus::tryFrom($status) : null);
    $label = $value?->label() ?? (is_string($status) && $status !== '' ? $status : '—');
    $color = $value?->color() ?? 'slate';

    /*
     * Maps the enum's legacy color names onto the four-color theme:
     * emerald = good, blue = informational, gold = special, yellow = attention,
     * red = reserved for absence/failure, neutral = non-working days.
     */
    $classes = [
        'green' => 'border-brand-200 bg-brand-50 text-brand-800',
        'teal' => 'border-brand-300 bg-brand-100 text-brand-900',
        'blue' => 'border-info-200 bg-info-50 text-info-800',
        'purple' => 'border-info-200 bg-info-50 text-info-700',
        'indigo' => 'border-gold-300 bg-gold-100 text-gold-800',
        'gold' => 'border-gold-300 bg-gold-100 text-gold-800',
        'yellow' => 'border-warn-200 bg-warn-50 text-warn-800',
        'orange' => 'border-warn-300 bg-warn-100 text-warn-900',
        'amber' => 'border-warn-200 bg-warn-50 text-warn-900',
        'red' => 'border-critical-200 bg-critical-50 text-critical-800',
        'slate' => 'border-line bg-canvas text-ink-soft',
    ][$color] ?? 'border-line bg-canvas text-ink-soft';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap $classes"]) }}>
    <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80" aria-hidden="true"></span>
    <span>{{ $label }}</span>
</span>
