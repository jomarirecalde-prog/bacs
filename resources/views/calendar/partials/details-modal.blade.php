{{--
    Read-only event details. Management actions render only when the server
    marked the viewer as able to manage events, and the payload for employees
    never contains edit/delete URLs in the first place.
--}}
<div x-show="open" x-cloak
     x-transition.opacity.duration.150ms
     class="modal-backdrop"
     @click.self="close()"
     @keydown.escape.window="close()"
     role="dialog" aria-modal="true" aria-labelledby="calendar-event-title">
    <div class="modal-panel max-w-lg" x-show="open" x-transition.duration.200ms>
        <template x-if="selected">
            <div>
                <template x-if="selected.banner">
                    <div class="mb-4">
                        <div class="cal-banner" :class="'cal-banner-' + selected.banner.tone" x-text="selected.banner.label"></div>
                    </div>
                </template>

                <div class="flex items-start gap-3">
                    <span class="cal-event-icon h-10 w-10 shrink-0" :class="'cal-event-icon-' + selected.tone">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" :d="selected.icon"/>
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 id="calendar-event-title" class="modal-title" x-text="selected.title"></h3>
                        <div class="mt-1.5 flex flex-wrap items-center gap-2">
                            <span class="badge" :class="'badge-' + selected.tone" x-text="selected.type"></span>
                            <span class="badge" :class="'badge-' + selected.status_tone" x-text="selected.status"></span>
                        </div>
                    </div>
                    <button type="button" @click="close()" class="icon-btn shrink-0" aria-label="Close">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <dl class="mt-5 space-y-3 border-t border-line pt-4 text-sm">
                    <div class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                        <dt class="shrink-0 text-muted sm:w-28">Date</dt>
                        <dd class="font-semibold text-ink" x-text="selected.date"></dd>
                    </div>
                    <div class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                        <dt class="shrink-0 text-muted sm:w-28">Time</dt>
                        <dd class="font-semibold text-ink" x-text="selected.time"></dd>
                    </div>
                    <template x-if="selected.location">
                        <div class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                            <dt class="shrink-0 text-muted sm:w-28">Location</dt>
                            <dd class="font-semibold text-ink" x-text="selected.location"></dd>
                        </div>
                    </template>
                    <template x-if="selected.supports_effect && selected.effect">
                        <div class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                            <dt class="shrink-0 text-muted sm:w-28">Attendance</dt>
                            <dd class="font-semibold text-ink" x-text="selected.effect"></dd>
                        </div>
                    </template>
                    <template x-if="selected.audience">
                        <div class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                            <dt class="shrink-0 text-muted sm:w-28">Audience</dt>
                            <dd class="font-semibold text-ink" x-text="selected.audience"></dd>
                        </div>
                    </template>
                    <template x-if="selected.created_by">
                        <div class="flex flex-col gap-1 sm:flex-row sm:gap-3">
                            <dt class="shrink-0 text-muted sm:w-28">Created by</dt>
                            <dd class="text-ink-soft"><span x-text="selected.created_by"></span> · <span x-text="selected.created_at"></span></dd>
                        </div>
                    </template>
                </dl>

                <template x-if="selected.description">
                    <div class="mt-4 border-t border-line pt-4">
                        <h4 class="text-xs font-bold uppercase tracking-wide text-muted">Description</h4>
                        <p class="mt-1.5 whitespace-pre-line text-sm text-ink-soft" x-text="selected.description"></p>
                    </div>
                </template>

                <template x-if="selected.instructions">
                    <div class="mt-4">
                        <div class="alert-gold">
                            <svg class="mt-0.5 h-4 w-4 shrink-0 text-gold-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>
                                <strong class="block font-bold">Additional instructions</strong>
                                <span class="whitespace-pre-line" x-text="selected.instructions"></span>
                            </span>
                        </div>
                    </div>
                </template>

                {{--
                    Action availability follows the payload the server built, not a
                    client-side flag: employees receive no management URLs at all.
                --}}
                <div class="modal-actions">
                    <div class="flex w-full flex-col gap-2 sm:flex-1 sm:flex-row sm:flex-wrap">
                        <template x-if="selected.show_url">
                            <a :href="selected.show_url" class="btn-outline btn-sm w-full sm:w-auto">View full details</a>
                        </template>
                        <template x-if="selected.edit_url">
                            <a :href="selected.edit_url" class="btn-secondary btn-sm w-full sm:w-auto">Edit</a>
                        </template>
                    </div>
                    <button type="button" @click="close()" class="btn-outline btn-sm w-full sm:w-auto">Close</button>
                </div>
            </div>
        </template>
    </div>
</div>
