@props([
    'url' => null,
    'label' => 'Back',
])

@php
    $url = is_string($url) ? trim($url) : $url;
    $url = $url !== '' ? $url : route('landing');
    $label = is_string($label) && trim($label) !== '' ? trim($label) : 'Back';
@endphp

<div class="safe-top absolute left-0 top-0 z-20 p-4 sm:p-6">
    <a
        href="{{ $url }}"
        class="inline-flex items-center gap-1.5 rounded-xl border border-line bg-surface/90 px-3 py-2 text-sm font-semibold text-ink shadow-sm backdrop-blur-sm transition hover:bg-surface"
    >
        <svg class="h-4 w-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        {{ $label }}
    </a>
</div>