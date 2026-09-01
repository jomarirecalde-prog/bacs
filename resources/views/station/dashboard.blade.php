@extends('layouts.station')

@section('title', $station->station_name)

@section('content')
<div class="min-h-[100dvh] bg-gradient-to-b from-shell-950 to-shell-900" x-data="stationKiosk({
    scanUrl: '{{ route('station.scan') }}',
    heartbeatUrl: '{{ route('station.heartbeat') }}',
    locked: {{ $station->isLocked() ? 'true' : 'false' }},
    csrf: '{{ csrf_token() }}'
})">
    <header class="flex flex-col gap-4 border-b border-white/10 bg-shell-950/60 px-4 py-4 backdrop-blur safe-top sm:flex-row sm:items-center sm:justify-between sm:px-5">
        <div class="flex min-w-0 items-center gap-3">
            <div class="brand-mark h-11 w-11 shrink-0 rounded-2xl text-lg">B</div>
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-[0.2em] text-gold-300">BACS Attendance Station</div>
                <h1 class="truncate text-lg font-extrabold text-white sm:text-2xl">{{ $station->station_name }}</h1>
                <p class="truncate text-sm text-brand-200/70">Location: {{ $station->location }}</p>
            </div>
        </div>
        <div class="flex items-center justify-between gap-4 sm:block sm:text-right">
            <div class="flex items-center gap-2 text-sm font-bold" :class="locked ? 'text-critical-300' : 'text-brand-300'">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="locked ? 'bg-critical-400' : 'bg-brand-400 animate-pulse'"></span>
                <span x-text="locked ? 'Station Locked' : 'Station Active'"></span>
            </div>
            <div class="text-right">
                <div class="text-sm text-brand-100/70" x-text="dateLabel">{{ $now->format('F j, Y') }}</div>
                <div class="text-2xl font-extrabold text-white tabular-nums sm:text-3xl" x-text="timeLabel">{{ $now->format('g:i A') }}</div>
            </div>
            <a href="{{ route('station.settings') }}" class="inline-flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-xs font-semibold text-brand-200/70 transition hover:bg-white/5 hover:text-white sm:mt-2">
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Station Settings
            </a>
        </div>
    </header>

    <main class="mx-auto grid max-w-6xl gap-4 p-3 safe-bottom sm:gap-6 sm:p-4 lg:grid-cols-2 lg:p-8">
        <section class="shell-panel p-4 sm:p-5">
            <h2 class="text-center text-base font-extrabold tracking-wide text-white sm:text-2xl">SCAN YOUR EMPLOYEE QR CODE</h2>
            <div class="relative mt-4 overflow-hidden rounded-3xl bg-black ring-1 ring-white/10">
                <video x-ref="video" class="aspect-4/3 w-full object-cover" playsinline muted autoplay></video>
                <div class="pointer-events-none absolute inset-0 border-[6px] border-brand-500/40"></div>
                <div class="pointer-events-none absolute inset-6 rounded-2xl border-2 border-gold-400/50"></div>
                <div x-show="busy" x-cloak class="absolute inset-0 flex items-center justify-center gap-3 bg-shell-950/70 text-sm font-bold text-brand-200 backdrop-blur-sm">
                    <span class="spinner"></span> Processing…
                </div>
            </div>
            <p class="mt-3 text-center text-xs text-brand-200/70" x-text="cameraStatus">Point the camera at the employee QR code.</p>
        </section>

        <section class="shell-panel flex min-h-[280px] items-center justify-center p-4 sm:min-h-[420px] sm:p-6">
            <div x-show="locked" x-cloak class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-critical-500/15 text-critical-300">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                </div>
                <h2 class="text-4xl font-extrabold text-critical-300">STATION LOCKED</h2>
                <p class="mt-3 text-brand-100/80">This attendance station has been temporarily disabled.</p>
                <p class="mt-1 text-brand-200/60">Please contact the administrator.</p>
            </div>

            <div x-show="!locked && !result" class="text-center">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-500/15 text-brand-300">
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2z"/></svg>
                </div>
                <p class="text-lg font-bold text-white">Ready to scan</p>
                <p class="mt-2 max-w-sm text-sm text-brand-200/70">The station automatically records AM Time In, AM Time Out, PM Time In, PM Time Out, then Overtime when applicable.</p>
            </div>

            <template x-if="!locked && result">
                <div class="w-full text-center">
                    <div class="text-xs font-bold uppercase tracking-[0.25em]" :class="resultTone" x-text="result.codeLabel"></div>
                    <h2 class="mt-2 text-3xl font-extrabold text-white sm:text-4xl" x-text="result.title"></h2>
                    <template x-if="result.photo">
                        <img :src="result.photo" alt="" class="mx-auto mt-5 h-28 w-28 rounded-3xl object-cover ring-4 ring-gold-400/30">
                    </template>
                    <div class="mt-4 text-2xl font-extrabold text-white" x-text="result.name"></div>
                    <div class="text-sm uppercase tracking-wide text-brand-200/70" x-text="result.employeeNumber"></div>
                    <div class="text-sm text-brand-200/70" x-text="[result.department, result.position].filter(Boolean).join(' · ')"></div>
                    <div class="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-brand-200/60">Recorded</div>
                        <div class="mt-1 text-xl font-extrabold text-gold-300" x-text="result.action || '—'"></div>
                        <div class="mt-1 text-lg font-bold text-white tabular-nums" x-text="result.time || '—'"></div>
                    </div>
                    <div class="mt-4 text-left">
                        <div class="text-xs font-bold uppercase tracking-wide text-brand-200/60">Today's Attendance</div>
                        <ul class="mt-2 space-y-1 text-sm">
                            <template x-for="item in result.progress" :key="item.label">
                                <li class="flex items-center justify-between gap-3 rounded-xl border border-white/5 bg-white/5 px-3 py-2">
                                    <span class="text-brand-100/80" x-text="item.label"></span>
                                    <span class="font-bold tabular-nums" :class="item.done ? 'text-brand-300' : 'text-brand-200/40'" x-text="item.done ? item.value : 'Pending'"></span>
                                </li>
                            </template>
                        </ul>
                    </div>
                    <div class="mt-4 rounded-2xl border border-gold-400/20 bg-gold-400/5 p-4" x-show="result.nextAction">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gold-300/70">Next Expected Action</div>
                        <div class="mt-1 text-lg font-extrabold text-gold-300" x-text="result.nextAction"></div>
                    </div>
                    <div class="mt-2 text-lg font-bold text-brand-300" x-text="result.status"></div>
                    <p class="mt-3 text-sm text-brand-200/70" x-text="result.message"></p>
                </div>
            </template>
        </section>
    </main>
</div>
@endsection
