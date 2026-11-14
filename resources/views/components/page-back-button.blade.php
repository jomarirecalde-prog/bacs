@props([
    'url' => null,
    'label' => 'Back',
    'fallback' => null,
    'show' => true,
])

@php
    $url = is_string($url) ? trim($url) : $url;
    $url = $url !== '' ? $url : null;
    $label = is_string($label) && trim($label) !== '' ? trim($label) : 'Back';
@endphp

<button
    type="button"
    id="page-back"
    class="icon-btn page-back-btn shrink-0{{ $show ? '' : ' hidden' }}"
    aria-label="{{ $label }}"
    title="{{ $label }}"
    @unless($show) hidden @endunless
    @if ($url) data-back-url="{{ $url }}" @endif
    @if ($fallback) data-back-fallback="{{ $fallback }}" @endif
>
    <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/>
    </svg>
    <span class="hidden text-sm font-semibold sm:inline">{{ $label }}</span>
</button>