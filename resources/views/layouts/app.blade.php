<!DOCTYPE html>
<html lang="en">
@if (request()->headers->get('X-BACS-Partial') === '1')
<body>
<div id="bacs-partial"
     data-title="@yield('title', 'Dashboard') · {{ config('app.name') }}"
     data-page-title="@yield('page-title', 'Dashboard')"
     data-page-subtitle="@yield('page-subtitle', 'Philippine Standard Time')"
     data-route="{{ optional(request()->route())->getName() }}">
    @if (session('success'))
        <div class="alert-success mb-4">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if (session('warning'))
        <div class="alert-warning mb-4">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
            <span>{{ session('warning') }}</span>
        </div>
    @endif
    @if (session('error'))
        <div class="alert-danger mb-4">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-critical-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if ($errors->any())
        <div class="alert-danger mb-4">
            <svg class="mt-0.5 h-4 w-4 shrink-0 text-critical-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif
    @yield('content')
</div>
</body>
</html>
@else
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="app-base" content="{{ url('/') }}">
    <meta name="user-id" content="{{ auth()->id() }}">
    <meta name="theme-color" content="#047857">
    <meta name="description" content="BACS Attendance Management System — daily time record, attendance, and leave management.">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    @include('partials.favicon')
    @include('partials.pwa-head')
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ sidebar: false }" :class="sidebar ? 'overflow-hidden' : ''">
    <div id="nav-progress" class="nav-progress" hidden></div>
    <div class="flex min-h-screen min-w-0">
        <aside class="fixed inset-y-0 left-0 z-40 flex w-[min(18rem,85vw)] max-w-72 flex-col bg-shell-900 text-brand-50 shadow-float transition duration-300 ease-out safe-top lg:w-72 lg:translate-x-0"
               :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               @keydown.escape.window="sidebar = false">
            <div class="flex h-16 shrink-0 items-center gap-3 border-b border-white/10 px-4 sm:px-6">
                <x-bacs-logo class="h-10 w-auto shrink-0" />
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-extrabold tracking-wide text-white">BACS DTR</div>
                    <div class="truncate text-[11px] text-brand-200/70">BACS Construction</div>
                </div>
                <button type="button" class="icon-btn -mr-1 text-brand-100/80 hover:bg-white/10 hover:text-white lg:hidden" @click="sidebar = false" aria-label="Close menu">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <nav class="flex-1 space-y-0.5 overflow-y-auto p-4">
                @php
                    $isAdmin = auth()->user()->isManagement();
                    $isFullAdmin = auth()->user()->isAdmin();

                    $icons = [
                        'dashboard' => 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6',
                        'employees' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
                        'departments' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1',
                        'device' => 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z',
                        'monitor' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
                        'clock' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
                        'document' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        'chart' => 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        'calendar' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
                        'calendar-events' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2zm7-8v3m0 0h-1.5m1.5 0H13',
                        'list' => 'M4 6h16M4 10h16M4 14h10M4 18h7',
                        'qr' => 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 4h2v2h-2v-2zm0-4h2v2h-2v-2z',
                        'shield' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
                        'cog' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z',
                        'user' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
                        'lock' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z',
                        'logout' => 'M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1',
                        'leave' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
                        'approve' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
                    ];

                    if ($isAdmin) {
                        $stationLinks = [];
                        if ($isFullAdmin) {
                            $stationLinks = [
                                ['admin.stations.index', 'Attendance Stations', $icons['device']],
                                ['admin.stations.monitoring', 'Station Monitoring', $icons['monitor']],
                            ];
                        }

                        $navGroups = [
                            ['label' => null, 'items' => [
                                ['admin.dashboard', 'Dashboard', $icons['dashboard']],
                            ]],
                            ['label' => 'Workforce', 'items' => [
                                ['admin.employees.index', 'Employees', $icons['employees']],
                                ['admin.departments.index', 'Departments', $icons['departments']],
                                ['admin.schedules.index', 'Schedules', $icons['calendar']],
                            ]],
                            ['label' => 'Timekeeping', 'items' => array_merge($stationLinks, [
                                ['admin.attendance.index', 'Attendance', $icons['clock']],
                                ['admin.attendance-corrections.index', 'DTR Corrections', $icons['document'], 'admin.attendance-corrections.*'],
                                ['admin.dtr.index', 'DTR Management', $icons['document']],
                                ['admin.reports.index', 'Reports', $icons['chart']],
                            ])],
                            ['label' => 'Calendar & Events', 'items' => [
                                ['admin.calendar.index', 'Calendar', $icons['calendar-events'], 'admin.calendar.index'],
                                ['admin.calendar.events.index', 'Manage Events', $icons['list'], 'admin.calendar.events*'],
                            ]],
                            ['label' => 'Leave Approvals', 'items' => [
                                ['leave.approvals.index', 'Pending Leave Requests', $icons['approve'], 'leave.approvals.index'],
                                ['leave.approvals.history', 'Approval History', $icons['document'], 'leave.approvals.history'],
                            ]],
                        ];

                        $leaveAdminItems = [
                            ['admin.leave.index', 'Leave Applications', $icons['leave'], 'admin.leave.index'],
                            ['admin.leave.reports', 'Leave Reports', $icons['chart'], 'admin.leave.reports'],
                        ];
                        if ($isFullAdmin) {
                            array_splice($leaveAdminItems, 1, 0, [
                                ['admin.leave.workflow', 'Leave Approval Configuration', $icons['list']],
                                ['admin.leave.entitlements', 'Employee Leave Balances', $icons['calendar']],
                            ]);
                        }
                        $navGroups[] = ['label' => 'Leave Management', 'items' => $leaveAdminItems];

                        if (auth()->user()->employee) {
                            $navGroups[] = ['label' => 'Personal', 'items' => [
                                ['employee.dashboard', 'My Time In / Out', $icons['clock']],
                                ['employee.dtr', 'My DTR', $icons['document']],
                                ['employee.attendance-corrections.index', 'DTR Corrections', $icons['document'], 'employee.attendance-corrections.*'],
                                ['employee.leave.apply', 'Apply for Leave', $icons['leave'], 'employee.leave.apply'],
                                ['employee.leave.index', 'My Leave Applications', $icons['list'], 'employee.leave.index'],
                                ['employee.leave.balances', 'My Leave Balances', $icons['calendar'], 'employee.leave.balances'],
                                ['employee.leave.calendar', 'Leave Calendar / History', $icons['calendar'], 'employee.leave.calendar'],
                                ['employee.calendar', 'My Calendar', $icons['calendar']],
                                ['employee.qr', 'My QR Code', $icons['qr']],
                            ]];
                        }

                        if ($isFullAdmin) {
                            $navGroups[] = ['label' => 'System', 'items' => [
                                ['admin.audit.index', 'Audit Logs', $icons['shield']],
                                ['admin.settings.index', 'Settings', $icons['cog']],
                            ]];
                        }
                    } else {
                        $navGroups = [
                            ['label' => null, 'items' => [
                                ['employee.dashboard', 'Dashboard', $icons['dashboard']],
                            ]],
                            ['label' => 'My Records', 'items' => [
                                ['employee.attendance', 'My Attendance', $icons['clock']],
                                ['employee.dtr', 'My DTR', $icons['document']],
                                ['employee.attendance-corrections.index', 'DTR Corrections', $icons['document'], 'employee.attendance-corrections.*'],
                                ['employee.qr', 'My QR Code', $icons['qr']],
                            ]],
                            ['label' => 'Calendar & Events', 'items' => [
                                ['employee.calendar', 'Calendar', $icons['calendar-events']],
                            ]],
                            ['label' => 'Leave Management', 'items' => [
                                ['employee.leave.apply', 'Apply for Leave', $icons['leave'], 'employee.leave.apply'],
                                ['employee.leave.index', 'My Leave Applications', $icons['list'], 'employee.leave.index'],
                                ['employee.leave.balances', 'My Leave Balances', $icons['calendar'], 'employee.leave.balances'],
                                ['employee.leave.calendar', 'Leave Calendar / History', $icons['calendar'], 'employee.leave.calendar'],
                            ]],
                        ];

                        if (auth()->user()->hasLeaveApprovalDuty()) {
                            $navGroups[] = ['label' => 'Leave Approvals', 'items' => [
                                ['leave.approvals.index', 'Pending Leave Requests', $icons['approve'], 'leave.approvals.index'],
                                ['leave.approvals.history', 'Approval History', $icons['document'], 'leave.approvals.history'],
                            ]];
                        }
                    }
                @endphp

                @foreach ($navGroups as $group)
                    @if ($group['label'])
                        <div class="nav-section-label">{{ $group['label'] }}</div>
                    @endif
                    @foreach ($group['items'] as $item)
                        @php
                            [$route, $label, $icon] = $item;
                            // Items may pin an explicit pattern when sibling routes share a prefix.
                            $pattern = $item[3] ?? str_replace('.index', '*', $route).'*';
                            $active = request()->routeIs($pattern) || request()->routeIs($route);
                        @endphp
                        <a href="{{ route($route) }}" @click="sidebar = false" class="nav-link {{ $active ? 'nav-link-active' : 'nav-link-idle' }}">
                            <svg class="h-5 w-5 shrink-0 {{ $active ? 'text-gold-300' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg>
                            <span class="truncate">{{ $label }}</span>
                        </a>
                    @endforeach
                @endforeach
            </nav>

            <div class="shrink-0 space-y-0.5 border-t border-white/10 p-4">
                @php $profileActive = request()->routeIs('profile.show'); @endphp
                <a href="{{ route('profile.show') }}" @click="sidebar = false" class="nav-link {{ $profileActive ? 'nav-link-active' : 'nav-link-idle' }}">
                    <svg class="h-5 w-5 shrink-0 {{ $profileActive ? 'text-gold-300' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['user'] }}"/></svg>
                    <span>My Profile</span>
                </a>
                @unless ($isAdmin)
                    @php $passwordActive = request()->routeIs('profile.password*'); @endphp
                    <a href="{{ route('profile.password') }}" @click="sidebar = false" class="nav-link {{ $passwordActive ? 'nav-link-active' : 'nav-link-idle' }}">
                        <svg class="h-5 w-5 shrink-0 {{ $passwordActive ? 'text-gold-300' : '' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['lock'] }}"/></svg>
                        <span>Change Password</span>
                    </a>
                @endunless
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="nav-link nav-link-idle w-full cursor-pointer">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icons['logout'] }}"/></svg>
                        <span>Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        <div class="min-h-screen min-w-0 flex-1 lg:pl-72">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-2 border-b border-line bg-surface/90 px-3 backdrop-blur-md safe-top sm:gap-4 sm:px-4 lg:px-8">
                <div class="flex min-w-0 flex-1 items-center gap-2 sm:gap-3">
                    <button type="button" class="icon-btn shrink-0 lg:hidden" @click="sidebar = true" aria-label="Open menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div class="min-w-0">
                        <h1 id="page-heading" class="truncate text-base font-extrabold tracking-tight text-ink">@yield('page-title', 'Dashboard')</h1>
                        <p id="page-subheading" class="truncate text-xs text-muted">@yield('page-subtitle', 'Philippine Standard Time')</p>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1.5 sm:gap-3">
                    <div class="hidden items-center gap-1.5 rounded-full border border-line bg-surface px-2.5 py-1 text-[11px] font-semibold sm:inline-flex"
                         x-data
                         :class="$store.pwa.online ? 'text-brand-700' : 'text-critical-600'"
                         :title="$store.pwa.online ? 'Online' : 'Offline'"
                         role="status">
                        <span class="inline-block h-2 w-2 rounded-full"
                              :class="$store.pwa.online ? 'bg-brand-500' : 'bg-critical-500'"
                              aria-hidden="true"></span>
                        <span x-text="$store.pwa.connectionLabel"></span>
                    </div>

                    <div class="mr-0.5 hidden text-right sm:mr-1 sm:block" x-data="manilaClock()">
                        <div class="text-[11px] text-muted" x-text="dateLabel"></div>
                        <div class="text-sm font-bold text-brand-700 tabular-nums" x-text="timeLabel"></div>
                    </div>

                    <div class="relative" x-data="notificationBell(@js($notificationBell))">
                        <button @click="toggle()" class="icon-btn relative" aria-label="Notifications">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            <span x-show="unread > 0" x-cloak
                                  class="absolute right-0.5 top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-warn-400 px-1 text-[10px] font-bold text-warn-900 ring-2 ring-white"
                                  x-text="unread > 99 ? '99+' : unread"></span>
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak x-transition.opacity.duration.150ms
                             class="dropdown-panel absolute right-0 z-50 mt-2 w-80 max-w-[calc(100vw-2rem)]">
                            <div class="dropdown-header">
                                <span class="text-sm font-bold text-ink">Notifications</span>
                                <button type="button" @click="markAllRead()" class="cursor-pointer text-xs font-semibold text-info-700 hover:text-info-800 hover:underline">Mark all read</button>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                <template x-for="note in items" :key="note.id">
                                    <a :href="note.link || '#'" @click="markRead(note, $event)"
                                       class="block border-b border-line/70 px-4 py-3 transition last:border-b-0 hover:bg-brand-50/60"
                                       :class="note.unread ? 'border-l-2 border-l-warn-400 bg-warn-50/50' : ''">
                                        <div class="text-sm font-semibold text-ink" x-text="note.title"></div>
                                        <div class="mt-0.5 text-xs text-muted" x-text="note.message"></div>
                                        <div class="mt-1 text-[11px] text-faint" x-text="note.created_at"></div>
                                    </a>
                                </template>
                                <div x-show="items.length === 0" class="px-4 py-10 text-center">
                                    <div class="mx-auto mb-2 flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-500">
                                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                                    </div>
                                    <p class="text-sm text-muted">No notifications yet.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 border-l border-line pl-2 sm:gap-2.5 sm:pl-3">
                        <div class="hidden max-w-[8rem] truncate text-right sm:block sm:max-w-none">
                            <div id="header-user-name" class="truncate text-sm font-bold leading-tight text-ink">{{ auth()->user()->name }}</div>
                            <div class="truncate text-[11px] font-medium text-info-700">{{ auth()->user()->role?->label() }}</div>
                        </div>
                        @if (auth()->user()->employee?->photo)
                            <img id="header-avatar" src="{{ auth()->user()->employee->photoUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-brand-100">
                        @else
                            <div id="header-avatar-fallback" class="brand-mark h-9 w-9 text-sm">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <img id="header-avatar" src="" alt="" class="hidden h-9 w-9 shrink-0 rounded-full object-cover ring-2 ring-brand-100">
                        @endif
                    </div>
                </div>
            </header>

            <main id="app-main" class="min-w-0 max-w-full overflow-x-hidden p-3 sm:p-4 lg:p-8">
                @if (session('success'))
                    <div class="alert-success mb-4">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif
                @if (session('warning'))
                    <div class="alert-warning mb-4">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-warn-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                        <span>{{ session('warning') }}</span>
                    </div>
                @endif
                @if (session('error'))
                    <div class="alert-danger mb-4">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-critical-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert-danger mb-4">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-critical-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                        <span>{{ $errors->first() }}</span>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <script>
        (function () {
            function labelTables(root) {
                (root || document).querySelectorAll('.table-wrap:not(.table-scroll-mobile) .data-table').forEach(function (table) {
                    var headers = Array.prototype.map.call(
                        table.querySelectorAll('thead th'),
                        function (th) { return th.textContent.trim(); }
                    );
                    if (!headers.length) return;
                    table.querySelectorAll('tbody tr').forEach(function (row) {
                        Array.prototype.forEach.call(row.querySelectorAll('td'), function (cell, index) {
                            if (cell.colSpan > 1) {
                                cell.dataset.label = '';
                                return;
                            }
                            if (headers[index]) {
                                cell.dataset.label = headers[index];
                            }
                        });
                    });
                });
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function () { labelTables(document.getElementById('app-main')); });
            } else {
                labelTables(document.getElementById('app-main'));
            }
            window.addEventListener('bacs:pageshow', function () { labelTables(document.getElementById('app-main')); });
        })();
    </script>

    @include('partials.pwa-ui')

    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-30 bg-shell-950/55 backdrop-blur-sm lg:hidden" @click="sidebar = false" aria-hidden="true"></div>

    <div x-data="{ show: false, message: '', type: 'success', timer: null }"
         x-on:dtr-toast.window="show = true; message = $event.detail.message; type = $event.detail.type || 'success'; clearTimeout(timer); timer = setTimeout(() => show = false, $event.detail.duration || 3500)"
         x-show="show" x-cloak
         x-transition.duration.200ms
         class="fixed bottom-4 left-4 right-4 z-50 mx-auto max-w-sm rounded-2xl px-4 py-3 text-sm font-semibold shadow-float safe-bottom sm:bottom-6 sm:left-auto sm:right-6"
         :class="type === 'success' ? 'bg-brand-600 text-white' : (type === 'error' ? 'bg-critical-600 text-white' : (type === 'warning' ? 'bg-warn-400 text-warn-900' : 'bg-info-600 text-white'))"
         x-text="message"></div>
</body>
</html>
@endif
