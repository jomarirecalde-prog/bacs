@props(['label', 'value', 'hint' => null, 'tone' => 'slate', 'icon' => null])

@php
    /*
     * Tone drives the accent rail + icon chip only; the card body stays neutral
     * so dashboards read as one calm surface.
     *   brand  -> emerald : totals, healthy/success metrics
     *   info   -> blue    : informational counts
     *   gold   -> gold    : featured / headline metrics
     *   warn   -> yellow  : pending, late, needs attention
     *   critical -> red   : absences, failures
     */
    $tones = [
        'slate' => ['accent' => 'border-t-line-strong', 'chip' => 'stat-icon bg-canvas text-muted'],
        'brand' => ['accent' => 'card-accent-brand', 'chip' => 'stat-icon-brand'],
        'green' => ['accent' => 'card-accent-brand', 'chip' => 'stat-icon-brand'],
        'teal' => ['accent' => 'card-accent-brand', 'chip' => 'stat-icon-brand'],
        'info' => ['accent' => 'card-accent-info', 'chip' => 'stat-icon-info'],
        'blue' => ['accent' => 'card-accent-info', 'chip' => 'stat-icon-info'],
        'gold' => ['accent' => 'card-accent-gold', 'chip' => 'stat-icon-gold'],
        'warn' => ['accent' => 'card-accent-warn', 'chip' => 'stat-icon-warn'],
        'yellow' => ['accent' => 'card-accent-warn', 'chip' => 'stat-icon-warn'],
        'orange' => ['accent' => 'card-accent-warn', 'chip' => 'stat-icon-warn'],
        'critical' => ['accent' => 'border-t-2 border-t-critical-500', 'chip' => 'stat-icon-critical'],
        'red' => ['accent' => 'border-t-2 border-t-critical-500', 'chip' => 'stat-icon-critical'],
    ];
    $t = $tones[$tone] ?? $tones['slate'];

    $icons = [
        'users' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
        'check' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        'warning' => 'M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z',
        'x' => 'M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z',
        'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
        'star' => 'M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.783-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z',
        'building' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1',
        'chart' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
    ];
    $iconPath = $icon ? ($icons[$icon] ?? null) : null;
@endphp

<div {{ $attributes->merge(['class' => "card card-interactive {$t['accent']} p-5"]) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <div class="stat-label">{{ $label }}</div>
            <div class="stat-value">{{ $value }}</div>
            @if ($hint)
                <div class="stat-hint">{{ $hint }}</div>
            @endif
        </div>
        @if ($iconPath)
            <div class="{{ $t['chip'] }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $iconPath }}"/></svg>
            </div>
        @endif
    </div>
    {{ $slot ?? '' }}
</div>
