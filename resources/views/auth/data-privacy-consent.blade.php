@extends('layouts.guest')

@section('title', 'Data Privacy Notice')

@section('content')
<div class="fixed inset-0 z-50 flex min-h-screen items-end justify-center overflow-y-auto bg-shell-950/80 p-0 backdrop-blur-sm sm:items-center sm:p-4"
     role="presentation"
     x-data="{
         acknowledged: false,
         init() {
             this.$nextTick(() => this.$refs.dialog?.focus());
             document.body.classList.add('overflow-hidden');
         },
         onSubmit(e) {
             if (!this.acknowledged) e.preventDefault();
         },
         decline() { this.$refs.declineForm.submit(); },
     }"
     x-init="init()"
     @keydown.escape.prevent
     @click.self.prevent>
    <div class="modal-panel w-full max-w-2xl sm:rounded-2xl"
         role="dialog"
         aria-modal="true"
         aria-labelledby="privacy-notice-title"
         aria-describedby="privacy-notice-body"
         x-ref="dialog"
         tabindex="-1">
        <h1 id="privacy-notice-title" class="text-lg font-bold text-ink sm:text-xl">
            {{ \App\Support\PrivacyNotice::title() }}
        </h1>
        <p class="mt-1 text-xs text-muted">Notice version {{ \App\Support\PrivacyNotice::version() }}</p>

        <div id="privacy-notice-body" class="prose prose-sm mt-5 max-w-none space-y-5 text-sm text-ink-secondary">
            @foreach (\App\Support\PrivacyNotice::sections() as $section)
                <section>
                    <h2 class="text-sm font-bold text-ink">{{ $section['heading'] }}</h2>
                    @foreach ($section['paragraphs'] as $paragraph)
                        <p class="mt-2 leading-relaxed">{{ $paragraph }}</p>
                    @endforeach
                    @if (! empty($section['bullets']))
                        <ul class="mt-2 list-disc space-y-1 pl-5">
                            @foreach ($section['bullets'] as $bullet)
                                <li>{{ $bullet }}</li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>

        @if ($policyUrl = \App\Support\PrivacyNotice::policyUrl())
            <p class="mt-4">
                <a href="{{ $policyUrl }}" target="_blank" rel="noopener noreferrer" class="link font-semibold">
                    Read the full privacy policy
                </a>
            </p>
        @endif

        <form class="mt-6 space-y-4" method="post" action="{{ route('privacy.consent.store') }}" @submit="onSubmit">
            @csrf
            <input type="hidden" name="action" value="granted" x-ref="actionGranted">
            <label class="flex cursor-pointer gap-3 rounded-xl border border-line bg-canvas/60 p-4">
                <input type="checkbox"
                       name="acknowledged"
                       value="1"
                       class="mt-1 h-4 w-4 shrink-0 rounded border-line text-brand-600 focus:ring-brand-500"
                       x-model="acknowledged"
                       required
                       aria-required="true">
                <span class="text-sm font-medium text-ink">
                    {{ \App\Support\PrivacyNotice::checkboxLabel() }}
                </span>
            </label>

            <div class="modal-actions sm:justify-between">
                <button type="button"
                        class="btn btn-outline w-full sm:w-auto"
                        @click="decline">
                    Decline
                </button>
                <button type="submit"
                        class="btn btn-primary w-full sm:w-auto"
                        :disabled="!acknowledged"
                        :class="!acknowledged ? 'opacity-50 cursor-not-allowed' : ''">
                    Agree and Continue
                </button>
            </div>
        </form>

        <form x-ref="declineForm" method="post" action="{{ route('privacy.consent.store') }}" class="hidden">
            @csrf
            <input type="hidden" name="action" value="declined">
        </form>
    </div>
</div>
@endsection
