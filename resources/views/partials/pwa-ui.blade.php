{{-- PWA install prompt, update notice, and offline status --}}
<div x-data x-cloak>
    {{-- Offline banner --}}
    <div x-show="!$store.pwa.online"
         x-transition.opacity.duration.200ms
         class="fixed inset-x-0 top-0 z-[60] border-b border-critical-700/30 bg-critical-600 px-3 py-2 text-center text-sm font-semibold text-white safe-top"
         role="status">
        <span class="inline-flex items-center gap-2">
            <span class="inline-block h-2 w-2 rounded-full bg-white/90" aria-hidden="true"></span>
            You are currently offline. Some features may be unavailable.
        </span>
    </div>

    {{-- Update available --}}
    <div x-show="$store.pwa.updateAvailable"
         x-transition.opacity.duration.200ms
         class="fixed inset-x-0 z-[59] border-b border-brand-700/20 bg-brand-700 px-3 py-2.5 text-white safe-top"
         :class="!$store.pwa.online ? 'top-10' : 'top-0'"
         role="alert">
        <div class="mx-auto flex max-w-4xl flex-wrap items-center justify-center gap-2 text-sm sm:justify-between">
            <span class="font-semibold">New version available</span>
            <button type="button" class="btn btn-sm bg-white text-brand-800 hover:bg-brand-50" @click="$store.pwa.applyUpdate()">
                Update Now
            </button>
        </div>
    </div>

    {{-- Install banner --}}
    <div x-show="$store.pwa.showBanner"
         x-transition.opacity.duration.200ms
         class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-md safe-bottom sm:bottom-6 sm:left-auto sm:right-6"
         role="dialog"
         aria-label="Install BACS App">
        <div class="overflow-hidden rounded-2xl border border-line bg-surface shadow-float">
            <div class="flex items-start gap-3 p-4">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-xl" aria-hidden="true">📱</div>
                <div class="min-w-0 flex-1">
                    <div class="font-extrabold text-ink">Install BACS App</div>
                    <p class="mt-1 text-sm text-muted">Access BACS faster from your device like a native app.</p>
                    <template x-if="$store.pwa.showIosGuide">
                        <div class="mt-3 rounded-xl bg-surface-50 p-3 text-xs text-muted">
                            <p class="font-bold text-ink">Add BACS to Home Screen</p>
                            <ol class="mt-2 list-decimal space-y-1 pl-4">
                                <li>Tap the Share button.</li>
                                <li>Select &ldquo;Add to Home Screen&rdquo;.</li>
                                <li>Tap &ldquo;Add&rdquo;.</li>
                            </ol>
                        </div>
                    </template>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="btn btn-primary btn-sm" @click="$store.pwa.install()" x-show="$store.pwa.canInstall">
                            Install App
                        </button>
                        <button type="button" class="btn btn-outline btn-sm" @click="$store.pwa.dismissBanner()">Not now</button>
                    </div>
                </div>
                <button type="button" class="icon-btn shrink-0 text-muted" @click="$store.pwa.dismissBanner()" aria-label="Dismiss">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
    </div>
</div>
