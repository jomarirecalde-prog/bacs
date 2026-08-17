@extends('layouts.station')

@section('title', $station->station_name)

@section('content')
<div class="min-h-screen" x-data="stationKiosk({
    scanUrl: '{{ route('station.scan') }}',
    heartbeatUrl: '{{ route('station.heartbeat') }}',
    locked: {{ $station->isLocked() ? 'true' : 'false' }},
    csrf: '{{ csrf_token() }}'
})">
    <header class="flex items-center justify-between gap-4 border-b border-white/10 px-5 py-4">
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-[0.2em] text-brand-300">BACS Attendance Station</div>
            <h1 class="text-xl font-extrabold sm:text-2xl">{{ $station->station_name }}</h1>
            <p class="text-sm text-slate-400">Location: {{ $station->location }}</p>
        </div>
        <div class="text-right">
            <div class="flex items-center justify-end gap-2 text-sm font-semibold" :class="locked ? 'text-red-300' : 'text-emerald-300'">
                <span class="h-2.5 w-2.5 rounded-full" :class="locked ? 'bg-red-400' : 'bg-emerald-400'"></span>
                <span x-text="locked ? 'Station Locked' : 'Station Active'"></span>
            </div>
            <div class="mt-1 text-sm text-slate-300" x-text="dateLabel">{{ $now->format('F j, Y') }}</div>
            <div class="text-2xl font-extrabold tabular-nums text-white sm:text-3xl" x-text="timeLabel">{{ $now->format('g:i A') }}</div>
            <a href="{{ route('station.settings') }}" class="mt-2 inline-block text-xs font-semibold text-slate-400 hover:text-white">Station Settings</a>
        </div>
    </header>

    <main class="mx-auto grid max-w-6xl gap-6 p-4 lg:grid-cols-2 lg:p-8">
        <section class="rounded-3xl border border-white/10 bg-slate-900 p-5">
            <h2 class="text-center text-lg font-extrabold tracking-wide sm:text-2xl">SCAN YOUR EMPLOYEE QR CODE</h2>
            <div class="relative mt-4 overflow-hidden rounded-3xl bg-black">
                <video x-ref="video" class="aspect-[4/3] w-full object-cover" playsinline muted autoplay></video>
                <div class="pointer-events-none absolute inset-0 border-[6px] border-brand-500/40"></div>
                <div x-show="busy" class="absolute inset-0 flex items-center justify-center bg-black/50 text-sm font-semibold">Processing…</div>
            </div>
            <p class="mt-3 text-center text-xs text-slate-400" x-text="cameraStatus">Point the camera at the employee QR code.</p>
        </section>

        <section class="flex min-h-[420px] items-center justify-center rounded-3xl border border-white/10 bg-slate-900 p-6">
            <div x-show="locked" x-cloak class="text-center">
                <h2 class="text-4xl font-extrabold text-red-300">STATION LOCKED</h2>
                <p class="mt-3 text-slate-300">This attendance station has been temporarily disabled.</p>
                <p class="mt-1 text-slate-400">Please contact the administrator.</p>
            </div>

            <div x-show="!locked && !result" class="text-center text-slate-400">
                <p class="text-lg font-semibold text-white">Ready to scan</p>
                <p class="mt-2 text-sm">Employees present their QR code. Time In and Time Out are recorded automatically using server time.</p>
            </div>

            <div x-show="!locked && result" x-cloak class="w-full text-center">
                <div class="text-xs font-bold uppercase tracking-[0.25em]" :class="resultTone" x-text="result.codeLabel"></div>
                <h2 class="mt-2 text-3xl font-extrabold sm:text-5xl" x-text="result.title"></h2>
                <img x-show="result.photo" :src="result.photo" alt="" class="mx-auto mt-5 h-28 w-28 rounded-3xl object-cover ring-4 ring-white/10">
                <div class="mt-4 text-2xl font-extrabold" x-text="result.name"></div>
                <div class="text-sm uppercase tracking-wide text-slate-400" x-text="[result.department, result.position].filter(Boolean).join(' · ')"></div>
                <div class="mt-6 grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl bg-white/5 p-4">
                        <div class="text-xs uppercase text-slate-400">Action</div>
                        <div class="text-xl font-extrabold" x-text="result.action || '—'"></div>
                    </div>
                    <div class="rounded-2xl bg-white/5 p-4">
                        <div class="text-xs uppercase text-slate-400">Time</div>
                        <div class="text-xl font-extrabold tabular-nums" x-text="result.time || '—'"></div>
                    </div>
                </div>
                <div class="mt-3 text-sm text-slate-300">
                    Time In: <span class="font-semibold text-white" x-text="result.timeIn || '—'"></span>
                    · Time Out: <span class="font-semibold text-white" x-text="result.timeOut || '—'"></span>
                </div>
                <div class="mt-2 text-lg font-bold" x-text="result.status"></div>
                <p class="mt-3 text-sm text-slate-300" x-text="result.message"></p>
            </div>
        </section>
    </main>
</div>
@endsection
