@props(['title' => 'Nothing to show', 'message' => 'No records match the current filters.'])

<div class="py-16 text-center">
    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-slate-100 text-slate-400">
        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
    </div>
    <h3 class="font-semibold text-slate-800">{{ $title }}</h3>
    <p class="mt-1 text-sm text-slate-500">{{ $message }}</p>
    {{ $slot }}
</div>
