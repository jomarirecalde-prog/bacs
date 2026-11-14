@extends('layouts.landing')

@section('title', 'Home')

@section('content')
@php
    $features = [
        [
            'title' => 'Centralized Access',
            'body' => 'Reach attendance, DTR, leave, and essential HR services through one secure sign-in.',
            'icon' => 'M12 11c1.657 0 3-1.343 3-3V6a3 3 0 10-6 0v2c0 1.657 1.343 3 3 3zm0 2c-2.761 0-5 2.239-5 5v1h10v-1c0-2.761-2.239-5-5-5z',
        ],
        [
            'title' => 'Efficient Management',
            'body' => 'Streamline timekeeping, approvals, and digital workflows for employees and supervisors.',
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 8h6',
        ],
        [
            'title' => 'Secure Environment',
            'body' => 'Server-authoritative timestamps, audited sessions, and role-based authorization.',
            'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        ],
        [
            'title' => 'Accessible Platform',
            'body' => 'A responsive experience across desktop, tablet, and mobile—wherever work takes you.',
            'icon' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
        ],
    ];

    $trust = [
        ['Secure Access', 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z'],
        ['Centralized Services', 'M4 6h16M4 10h16M4 14h16M4 18h16'],
        ['Reliable Platform', 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
    ];
@endphp

<div x-data="{ open: false }">
    <header class="landing-nav">
        <div class="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
            <a href="{{ route('landing') }}" class="flex min-w-0 items-center gap-3">
                <x-bacs-logo class="h-10 w-auto shrink-0" />
                <div class="min-w-0 leading-tight">
                    <div class="truncate text-sm font-extrabold text-brand-50 sm:text-base">{{ config('app.name') }}</div>
                    <p class="truncate text-xs text-brand-200/65">{{ $companyName }}</p>
                </div>
            </a>

            <nav class="hidden items-center gap-1 md:flex" aria-label="Primary">
                <a href="#home" class="landing-nav-link">Home</a>
                <a href="#about" class="landing-nav-link">About</a>
                <a href="#features" class="landing-nav-link">Features</a>
                <a href="#announcements" class="landing-nav-link">Announcements</a>
                <a href="#contact" class="landing-nav-link">Contact</a>
                <a href="#sign-in" class="btn-primary btn-sm ml-2">Sign In</a>
            </nav>

            <div class="flex items-center gap-2 md:hidden">
                <a href="#sign-in" class="btn-primary btn-sm">Sign In</a>
                <button
                    type="button"
                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl text-brand-100 transition hover:bg-white/5"
                    @click="open = !open"
                    :aria-expanded="open"
                    aria-controls="mobile-nav"
                    aria-label="Toggle navigation"
                >
                    <svg x-show="!open" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    <svg x-show="open" x-cloak class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <nav
            id="mobile-nav"
            x-show="open"
            x-cloak
            class="border-t border-white/10 bg-shell-950 px-4 py-3 md:hidden"
            aria-label="Mobile"
        >
            <a href="#home" @click="open = false" class="landing-nav-link block">Home</a>
            <a href="#about" @click="open = false" class="landing-nav-link block">About</a>
            <a href="#features" @click="open = false" class="landing-nav-link block">Features</a>
            <a href="#announcements" @click="open = false" class="landing-nav-link block">Announcements</a>
            <a href="#contact" @click="open = false" class="landing-nav-link block">Contact</a>
        </nav>
    </header>

    <main>
        <section id="home" class="landing-hero scroll-mt-20">
            <div class="landing-hero__grid" aria-hidden="true"></div>
            <div class="landing-hero__beam" aria-hidden="true"></div>
            <div class="landing-hero__beam landing-hero__beam--gold" aria-hidden="true"></div>
            <div class="landing-hero__line" aria-hidden="true"></div>

            <div class="relative mx-auto grid max-w-6xl gap-12 px-4 py-14 sm:px-6 lg:grid-cols-2 lg:items-center lg:gap-14 lg:px-8 lg:py-20 xl:py-24">
                <div class="landing-reveal max-w-xl">
                    <p class="landing-kicker">Official digital portal</p>
                    <h1 class="mt-6 text-3xl font-extrabold leading-[1.12] tracking-tight text-brand-50 sm:text-4xl lg:text-5xl">
                        Your Digital Workplace.<br>
                        <span class="text-brand-300">Simplified</span><span class="text-gold-400">.</span>
                    </h1>
                    <p class="mt-5 text-base leading-relaxed text-brand-100/75 sm:text-lg">
                        Access your organization&rsquo;s digital services through one secure and centralized platform. Manage your activities, information, and essential services with ease.
                    </p>
                    <div class="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center">
                        <a href="#features" class="btn-primary btn-lg w-full sm:w-auto">Explore Platform</a>
                        <a href="#about" class="landing-btn-outline w-full sm:w-auto">Learn More</a>
                    </div>
                    <div class="mt-10 grid gap-3 sm:grid-cols-1">
                        @foreach ($trust as [$label, $path])
                            <div class="landing-trust">
                                <svg class="h-5 w-5 shrink-0 text-brand-300" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $path }}"/></svg>
                                <span class="text-sm font-medium text-brand-100/85">{{ $label }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative w-full max-w-md justify-self-center lg:max-w-none lg:justify-self-end">
                    @include('partials.landing.login-card')
                </div>
            </div>
        </section>

        <section id="features" class="landing-section landing-section--lift scroll-mt-24">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="landing-section-title">Platform features</p>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-brand-50 sm:text-3xl">Everything You Need, In One Place</h2>
                    <p class="mt-3 text-brand-100/70">A centralized digital platform designed to simplify everyday organizational processes.</p>
                </div>
                <div class="mt-12 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($features as $feature)
                        <article class="landing-feature-card">
                            <div class="landing-feature-icon">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $feature['icon'] }}"/></svg>
                            </div>
                            <h3 class="mt-4 text-base font-bold text-brand-50">{{ $feature['title'] }}</h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-brand-200/70">{{ $feature['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section id="about" class="landing-section landing-section--deep scroll-mt-24">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="grid gap-10 lg:grid-cols-2 lg:items-start lg:gap-14">
                    <div>
                        <p class="landing-section-title">About</p>
                        <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-brand-50 sm:text-3xl">Designed for a Better Digital Experience</h2>
                        <p class="mt-4 text-base leading-relaxed text-brand-100/75">
                            {{ config('app.name') }} is the workforce portal for <span class="text-brand-200">{{ $companyName }}</span>—bringing attendance, daily time records, leave, payroll-aligned reporting, and calendars into one authenticated environment.
                        </p>
                        <p class="mt-4 text-sm leading-relaxed text-brand-200/70">
                            The platform supports supervisors and HR with audited workflows while giving employees a clear view of their timekeeping and requests. Philippine Standard Time is authoritative across every record.
                        </p>
                        @if ($companyAddress)
                            <p class="mt-5 flex items-start gap-2 text-sm text-brand-100/80">
                                <svg class="mt-0.5 h-4 w-4 shrink-0 text-gold-400/90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>{{ $companyAddress }}</span>
                            </p>
                        @endif
                    </div>
                    <div class="landing-about-panel">
                        <div class="landing-divider-gold mb-4"></div>
                        <h3 class="text-sm font-bold uppercase tracking-wide text-gold-400/95">Why sign in here?</h3>
                        <ul class="mt-4 space-y-3 text-sm text-brand-100/80">
                            <li class="flex gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span>One entry point for employee and management dashboards</li>
                            <li class="flex gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span>Privacy-conscious processing aligned with RA 10173</li>
                            <li class="flex gap-3"><span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-brand-400"></span>Attendance stations and mobile-friendly workflows</li>
                        </ul>
                        <a href="#sign-in" class="btn-primary btn-sm mt-6">Sign In</a>
                    </div>
                </div>
            </div>
        </section>

        <section id="announcements" class="landing-section landing-section--lift scroll-mt-24">
            <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                <div class="max-w-2xl">
                    <p class="landing-section-title">Announcements</p>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-brand-50">Latest updates</h2>
                    <p class="mt-2 text-sm text-brand-100/70">Organization-wide notices from the company calendar. Sign in for your personal schedule.</p>
                </div>
                @if ($announcements->isEmpty())
                    <div class="landing-announce mt-8 p-10 text-center">
                        <p class="text-sm text-brand-200/65">No announcements are scheduled right now.</p>
                    </div>
                @else
                    <ul class="landing-announce mt-8 divide-y divide-white/10">
                        @foreach ($announcements as $event)
                            <li class="flex flex-col gap-3 p-5 sm:flex-row sm:items-start sm:justify-between sm:gap-6">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="inline-flex items-center rounded-lg border border-warn-500/30 bg-warn-500/10 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-warn-300">
                                            {{ $event->event_type->shortLabel() }}
                                        </span>
                                        @if ($event->location)
                                            <span class="truncate text-xs text-brand-200/50">{{ $event->location }}</span>
                                        @endif
                                    </div>
                                    <h3 class="mt-1 text-base font-bold text-brand-50">{{ $event->title }}</h3>
                                    @if ($event->description)
                                        <p class="mt-1 text-sm text-brand-200/65 line-clamp-2">{{ $event->description }}</p>
                                    @endif
                                </div>
                                <time class="shrink-0 text-sm font-semibold tabular-nums text-brand-300" datetime="{{ $event->start_date->toDateString() }}">
                                    {{ $event->start_date->format('M j, Y') }}
                                    @if ($event->end_date && ! $event->start_date->isSameDay($event->end_date))
                                        – {{ $event->end_date->format('M j, Y') }}
                                    @endif
                                </time>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        <section id="contact" class="landing-section landing-section--deep scroll-mt-24">
            <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-2 lg:gap-12 lg:px-8">
                <div>
                    <p class="landing-section-title">Contact &amp; support</p>
                    <h2 class="mt-3 text-2xl font-extrabold tracking-tight text-brand-50">We&rsquo;re here to help</h2>
                    <p class="mt-3 text-sm text-brand-100/70">For account access, password assistance, or HR inquiries, use the verified contacts below.</p>
                    <dl class="mt-6 space-y-4 text-sm">
                        <div class="flex gap-3">
                            <dt class="w-24 shrink-0 font-semibold text-brand-100">Organization</dt>
                            <dd class="text-brand-200/80">{{ $companyName }}</dd>
                        </div>
                        @if ($companyAddress)
                            <div class="flex gap-3">
                                <dt class="w-24 shrink-0 font-semibold text-brand-100">Office</dt>
                                <dd class="text-brand-200/80">{{ $companyAddress }}</dd>
                            </div>
                        @endif
                        @if ($contactEmail)
                            <div class="flex gap-3">
                                <dt class="w-24 shrink-0 font-semibold text-brand-100">Email</dt>
                                <dd><a href="mailto:{{ $contactEmail }}" class="landing-link">{{ $contactEmail }}</a></dd>
                            </div>
                        @endif
                        <div class="flex gap-3">
                            <dt class="w-24 shrink-0 font-semibold text-brand-100">DPO</dt>
                            <dd class="text-brand-200/80">
                                <a href="mailto:{{ $dpoEmail }}" class="landing-link">{{ $dpoEmail }}</a>
                                @if ($dpoPhone)
                                    <span class="text-brand-200/50"> · {{ $dpoPhone }}</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div id="privacy" class="landing-about-panel scroll-mt-24">
                    <p class="landing-section-title">Privacy policy</p>
                    <h3 class="mt-2 text-lg font-bold text-brand-50">{{ $privacyTitle }}</h3>
                    <p class="mt-3 text-sm leading-relaxed text-brand-100/70">
                        Sign-in requires acknowledgment of our data privacy notice. Personal data is processed for legitimate HR and workforce administration only.
                    </p>
                    @if ($privacyPolicyUrl)
                        <a href="{{ $privacyPolicyUrl }}" target="_blank" rel="noopener noreferrer" class="landing-link mt-4 inline-flex items-center gap-1.5 text-sm">
                            Read the full privacy policy
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    @else
                        <p class="mt-4 text-sm text-brand-200/65">The full notice is presented securely after you authenticate.</p>
                    @endif
                    <p class="mt-6 text-xs text-brand-200/50">Forgot your password? Use <a href="{{ route('password.request') }}" class="landing-link">self-service reset</a> when your account has a registered email, or contact HR or the DPO.</p>
                </div>
            </div>
        </section>
    </main>

    <footer class="landing-footer">
        <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid gap-10 lg:grid-cols-2 lg:gap-16">
                <div>
                    <a href="{{ route('landing') }}" class="inline-flex items-center gap-3">
                        <x-bacs-logo class="h-10 w-auto" />
                        <span class="text-sm font-extrabold text-brand-50">{{ config('app.name') }}</span>
                    </a>
                    <p class="mt-4 max-w-sm text-sm leading-relaxed text-brand-200/65">
                        Secure workforce timekeeping and HR services for {{ $companyName }}.
                    </p>
                    <div class="landing-divider-gold mt-6"></div>
                </div>
                <div class="grid grid-cols-2 gap-6 text-sm sm:grid-cols-3">
                    <div class="space-y-2">
                        <a href="#home" class="landing-footer-link block">Home</a>
                        <a href="#about" class="landing-footer-link block">About</a>
                        <a href="#features" class="landing-footer-link block">Features</a>
                    </div>
                    <div class="space-y-2">
                        <a href="#contact" class="landing-footer-link block">Contact</a>
                        <a href="#privacy" class="landing-footer-link block">Privacy Policy</a>
                        @if ($privacyPolicyUrl)
                            <a href="{{ $privacyPolicyUrl }}" target="_blank" rel="noopener noreferrer" class="landing-footer-link block">Policy document</a>
                        @endif
                    </div>
                    <div class="space-y-2 col-span-2 sm:col-span-1">
                        <a href="#sign-in" class="landing-footer-link block">Sign In</a>
                        <a href="{{ route('station.login') }}" class="landing-footer-link block">Station login</a>
                    </div>
                </div>
            </div>
            <div class="mt-10 flex flex-col gap-2 border-t border-white/10 pt-6 text-center text-xs text-brand-200/50 sm:flex-row sm:items-center sm:justify-between sm:text-left">
                <p>&copy; {{ date('Y') }} {{ $companyName }}. All rights reserved.</p>
                <p>{{ config('app.name') }} · Authorized personnel only</p>
            </div>
        </div>
    </footer>
</div>
@endsection
