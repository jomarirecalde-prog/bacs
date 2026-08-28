@php $affectsAttendance = $event->affectsAttendance(); @endphp

<div x-data="{ confirming: false }" class="inline-flex">
    <button type="button" @click="confirming = true" class="btn-outline-danger btn-sm">Delete</button>

    <div x-show="confirming" x-cloak
         x-transition.opacity.duration.150ms
         class="modal-backdrop"
         @click.self="confirming = false"
         @keydown.escape.window="confirming = false"
         role="dialog" aria-modal="true">
        <div class="modal-panel max-w-md text-left" x-show="confirming" x-transition.duration.200ms>
            <div class="flex items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-critical-100 text-critical-600">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                </span>
                <div class="min-w-0">
                    <h3 class="modal-title">Delete this event?</h3>
                    <p class="mt-1.5 text-sm text-muted">
                        <span class="font-semibold text-ink">{{ $event->title }}</span> ({{ $event->dateLabel() }}) will be removed from the company calendar.
                    </p>
                </div>
            </div>

            @if ($affectsAttendance)
                <div class="alert-warning mt-4">
                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                    <span>
                        <strong class="block font-bold">This event affects attendance.</strong>
                        It currently marks {{ $event->dateLabel() }} as
                        <strong>{{ $event->attendance_effect->label() }}</strong>.
                        Deleting it makes those dates count as regular working days in the DTR and reports,
                        so employees without a time-in may then appear absent.
                        Existing attendance records are not modified.
                    </span>
                </div>
            @endif

            <div class="modal-actions">
                <button type="button" @click="confirming = false" class="btn-outline btn-sm">Cancel</button>
                <form method="POST" action="{{ route('admin.calendar.events.destroy', $event) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger btn-sm">Delete event</button>
                </form>
            </div>
        </div>
    </div>
</div>
