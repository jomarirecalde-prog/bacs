@props(['label' => 'Actions'])

<div {{ $attributes->merge(['class' => 'relative inline-block text-left']) }} x-data="{ open: false }">
    <button type="button"
            @click="open = !open"
            @keydown.escape.window="open = false"
            class="btn-outline btn-sm inline-flex min-h-9 w-full items-center justify-center gap-1.5 sm:w-auto"
            :aria-expanded="open">
        <span>{{ $label }}</span>
        <svg class="h-3.5 w-3.5 transition" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div x-show="open"
         @click.outside="open = false"
         x-cloak
         x-transition.opacity.duration.150ms
         class="dropdown-panel absolute right-0 z-50 mt-1 min-w-[10rem] max-w-[calc(100vw-2rem)]">
        <div class="py-1" @click="open = false">
            {{ $slot }}
        </div>
    </div>
</div>
