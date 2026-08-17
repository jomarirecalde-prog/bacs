<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ config('app.name') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700,800" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen" x-data="{ sidebar: false }">
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-40 w-72 bg-slate-950 text-slate-200 transform transition lg:translate-x-0"
               :class="sidebar ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'">
            <div class="flex h-16 items-center gap-3 px-6 border-b border-white/10">
                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white font-extrabold">D</div>
                <div>
                    <div class="text-sm font-bold text-white tracking-wide">BACS DTR</div>
                    <div class="text-[11px] text-slate-400">BACS Construction</div>
                </div>
            </div>
            <nav class="p-4 space-y-1 overflow-y-auto h-[calc(100vh-8rem)]">
                @php
                    $isAdmin = auth()->user()->isManagement();
                    $adminLinks = [
                        ['admin.dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                        ['admin.employees.index', 'Employees', 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
                        ['admin.departments.index', 'Departments', 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1'],
                    ];
                    if (auth()->user()->isAdmin()) {
                        $adminLinks[] = ['admin.stations.index', 'Attendance Stations', 'M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z'];
                        $adminLinks[] = ['admin.stations.monitoring', 'Station Monitoring', 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'];
                    }
                    $adminLinks = array_merge($adminLinks, [
                        ['admin.attendance.index', 'Attendance', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['admin.dtr.index', 'DTR Management', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['admin.reports.index', 'Reports', 'M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['admin.schedules.index', 'Schedules', 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
                    ]);
                    $employeeLinks = [
                        ['employee.dashboard', 'Dashboard', 'M3 12l9-9 9 9M5 10v10a1 1 0 001 1h3m10-11v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
                        ['employee.attendance', 'My Attendance', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
                        ['employee.dtr', 'My DTR', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                        ['employee.qr', 'My QR Code', 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 4h2v2h-2v-2zm0-4h2v2h-2v-2z'],
                    ];
                    $links = $isAdmin ? $adminLinks : $employeeLinks;
                    if ($isAdmin && auth()->user()->employee) {
                        $links[] = ['employee.dashboard', 'My Time In / Out', 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'];
                        $links[] = ['employee.dtr', 'My DTR', 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'];
                        $links[] = ['employee.qr', 'My QR Code', 'M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h2v2h-2v-2zm4 0h2v2h-2v-2zm-4 4h2v2h-2v-2zm4 4h2v2h-2v-2zm0-4h2v2h-2v-2z'];
                    }
                @endphp

                @foreach ($links as [$route, $label, $icon])
                    <a href="{{ route($route) }}"
                       class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm {{ request()->routeIs(str_replace('.index','*', $route).'*') || request()->routeIs($route) ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/></svg>
                        {{ $label }}
                    </a>
                @endforeach

                @if ($isAdmin && auth()->user()->isAdmin())
                    <a href="{{ route('admin.audit.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm {{ request()->routeIs('admin.audit.*') ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Audit Logs
                    </a>
                    <a href="{{ route('admin.settings.index') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm {{ request()->routeIs('admin.settings.*') ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        Settings
                    </a>
                @endif

                <div class="pt-4 mt-4 border-t border-white/10 space-y-1">
                    <a href="{{ route('profile.show') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm {{ request()->routeIs('profile.show') ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        My Profile
                    </a>
                    @unless ($isAdmin)
                        <a href="{{ route('profile.password') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm {{ request()->routeIs('profile.password*') ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white' }}">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Change Password
                        </a>
                    @endunless
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="w-full flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm text-slate-400 hover:bg-white/5 hover:text-white">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                            Logout
                        </button>
                    </form>
                </div>
            </nav>
        </aside>

        <div class="flex-1 lg:pl-72 min-h-screen">
            <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-4 border-b border-slate-200 bg-white/90 px-4 backdrop-blur lg:px-8">
                <div class="flex items-center gap-3">
                    <button class="lg:hidden rounded-lg p-2 hover:bg-slate-100" @click="sidebar = true" aria-label="Open menu">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <div>
                        <h1 class="text-base font-bold text-slate-900">@yield('page-title', 'Dashboard')</h1>
                        <p class="text-xs text-slate-500">@yield('page-subtitle', 'Philippine Standard Time')</p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden sm:block text-right mr-2" x-data="manilaClock()">
                        <div class="text-xs text-slate-500" x-text="dateLabel"></div>
                        <div class="text-sm font-bold tabular-nums" x-text="timeLabel"></div>
                    </div>
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="relative rounded-xl p-2 hover:bg-slate-100" aria-label="Notifications">
                            <svg class="h-5 w-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                            @if (($unreadNotifications ?? 0) > 0)
                                <span class="absolute top-1 right-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{{ $unreadNotifications }}</span>
                            @endif
                        </button>
                        <div x-show="open" @click.outside="open = false" x-cloak class="absolute right-0 mt-2 w-80 card p-0 overflow-hidden z-50">
                            <div class="flex items-center justify-between px-4 py-3 border-b">
                                <span class="text-sm font-semibold">Notifications</span>
                                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="text-xs text-brand-700">Mark all read</button></form>
                            </div>
                            <div class="max-h-80 overflow-y-auto">
                                @forelse ($latestNotifications ?? [] as $note)
                                    <a href="{{ $note->link ?: '#' }}" class="block px-4 py-3 hover:bg-slate-50 {{ $note->isUnread() ? 'bg-brand-50/60' : '' }}">
                                        <div class="text-sm font-semibold">{{ $note->title }}</div>
                                        <div class="text-xs text-slate-500">{{ $note->message }}</div>
                                    </a>
                                @empty
                                    <div class="px-4 py-8 text-center text-sm text-slate-500">No notifications yet.</div>
                                @endforelse
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <div class="hidden sm:block text-right">
                            <div class="text-sm font-semibold leading-tight">{{ auth()->user()->name }}</div>
                            <div class="text-[11px] text-slate-500">{{ auth()->user()->role?->label() }}</div>
                        </div>
                        <div class="h-9 w-9 rounded-full bg-brand-700 text-white flex items-center justify-center text-sm font-bold">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                    </div>
                </div>
            </header>

            <main class="p-4 lg:p-8">
                @if (session('success'))
                    <div class="mb-4 rounded-xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm text-emerald-800">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-4 rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-xl bg-red-50 border border-red-100 px-4 py-3 text-sm text-red-800">
                        {{ $errors->first() }}
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>

    <div x-show="sidebar" class="fixed inset-0 z-30 bg-black/40 lg:hidden" @click="sidebar = false" x-cloak></div>

    <div x-data="{ show: false, message: '', type: 'success' }"
         x-on:dtr-toast.window="show = true; message = $event.detail.message; type = $event.detail.type || 'success'; setTimeout(() => show = false, 3500)"
         x-show="show" x-cloak
         class="fixed bottom-6 right-6 z-50 rounded-2xl px-4 py-3 text-sm font-medium text-white shadow-lg"
         :class="type === 'success' ? 'bg-emerald-600' : (type === 'error' ? 'bg-red-600' : 'bg-slate-800')"
         x-text="message"></div>
    <style>[x-cloak]{display:none !important}</style>
</body>
</html>
