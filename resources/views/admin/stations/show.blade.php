@extends('layouts.app')

@section('title', $station->station_code)
@section('page-title', $station->station_name)
@section('page-subtitle', $station->station_code.' · '.$station->location)

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card h-fit overflow-hidden {{ $station->isLocked() ? 'card-accent-warn' : 'card-accent-brand' }}">
        <div class="card-header">
            <h2 class="card-title">Station Overview</h2>
            @php $stationStatus = $station->status->value; @endphp
            <span class="{{ $stationStatus === 'active' ? 'badge-brand' : ($stationStatus === 'locked' ? 'badge-warn' : 'badge-neutral') }}">
                <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                {{ $station->status->label() }}
            </span>
        </div>

        <dl class="divide-y divide-line px-5 text-sm">
            @foreach ([
                ['Station ID', $station->station_code],
                ['Location', $station->location],
                ['Device', $station->device_status->label()],
                ['Presence', $station->presenceLabel()],
                ['Idle timeout', $station->idleTimeoutLabel()],
                ['Last seen', $station->last_seen_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—'],
                ['Last scan', $station->last_scan_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—'],
                ['Bound at', $binding?->bound_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—'],
            ] as [$term, $definition])
                <div class="flex justify-between gap-4 py-2.5">
                    <dt class="shrink-0 text-muted">{{ $term }}</dt>
                    <dd class="text-right font-semibold text-ink">{{ $definition }}</dd>
                </div>
            @endforeach
            <div class="py-3">
                <dt class="text-muted">Description</dt>
                <dd class="mt-1 text-ink">{{ $station->description ?: 'No description.' }}</dd>
            </div>
        </dl>

        <div class="card-footer space-y-3">
            <div class="flex flex-wrap gap-2">
                <a class="btn-outline-info btn-sm" href="{{ route('admin.stations.edit', $station) }}">Edit</a>
                <a class="btn-outline btn-sm" href="{{ route('admin.stations.activity', $station) }}">View Activity</a>
                <a class="btn-outline btn-sm" href="{{ route('admin.stations.attendance', $station) }}">View Attendance</a>
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($station->isInactive())
                    <form method="POST" action="{{ route('admin.stations.activate', $station) }}">@csrf<button type="submit" class="btn-primary btn-sm">Activate</button></form>
                @else
                    <form method="POST" action="{{ route('admin.stations.deactivate', $station) }}" onsubmit="return confirm('Deactivate this station?')">@csrf<button type="submit" class="btn-outline btn-sm">Deactivate</button></form>
                @endif
                @if ($station->isLocked())
                    <form method="POST" action="{{ route('admin.stations.unlock', $station) }}">@csrf<button type="submit" class="btn-primary btn-sm">Unlock Station</button></form>
                @else
                    <form method="POST" action="{{ route('admin.stations.lock', $station) }}" onsubmit="return confirm('Lock this station? The scanner will stop recording attendance.')">@csrf<button type="submit" class="btn-warning btn-sm">Lock Station</button></form>
                @endif
            </div>
        </div>
    </div>

    <div class="space-y-6 lg:col-span-2">
        <div class="card overflow-hidden" x-data="{ resetDevice: false, resetPassword: false }">
            <div class="card-header">
                <h3 class="card-title">Device Binding</h3>
                <span class="{{ $station->isBound() ? 'badge-gold' : 'badge-neutral' }}">{{ $station->isBound() ? 'Bound' : 'Unbound' }}</span>
            </div>

            <div class="space-y-4 p-5">
                <p class="text-sm text-muted">Once bound, this Station ID can only operate from the registered device until you reset it.</p>

                <div class="rounded-xl border border-line bg-canvas/60 p-4 text-sm">
                    <div class="text-ink">Current Device: <strong class="font-bold">{{ $station->device_status->label() }}</strong></div>
                    <div class="mt-0.5 text-muted">Current Location: {{ $station->location }}</div>
                </div>

                @if ($station->isBound())
                    <button class="btn-outline-danger" type="button" @click="resetDevice = true">Reset / Transfer Device</button>
                @else
                    <div class="alert-info">
                        <svg class="mt-0.5 h-4 w-4 shrink-0 text-info-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>This station is unbound. The next successful login will bind it to that device.</span>
                    </div>
                @endif

                <div class="divider pt-1"></div>

                <div>
                    <h3 class="text-sm font-bold text-ink">Station Password</h3>
                    <p class="mt-1 text-sm text-muted">The current password cannot be displayed. Reset it to issue a new one.</p>
                    <button class="btn-outline-info mt-3" type="button" @click="resetPassword = true">Reset Password</button>
                </div>
            </div>

            <div x-show="resetDevice" x-cloak class="modal-backdrop flex items-center justify-center p-4" @click.self="resetDevice = false">
                <div class="modal-panel">
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-critical-100 text-critical-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01M5.07 19h13.86a2 2 0 001.72-3L13.72 4a2 2 0 00-3.44 0L3.35 16a2 2 0 001.72 3z"/></svg>
                    </div>
                    <h3 class="modal-title">Reset Station Device?</h3>
                    <dl class="mt-3 space-y-1 text-sm">
                        <div class="flex justify-between gap-4"><dt class="text-muted">Station</dt><dd class="font-bold text-ink">{{ $station->station_code }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted">Current Device</dt><dd class="font-semibold text-ink">Bound</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-muted">Current Location</dt><dd class="text-right font-semibold text-ink">{{ $station->location }}</dd></div>
                    </dl>
                    <p class="mt-3 text-sm text-muted">Resetting this device will allow the station to be registered on another device. The current device will no longer be authorized.</p>
                    <form method="POST" action="{{ route('admin.stations.unbind', $station) }}" class="modal-actions">
                        @csrf
                        <input type="hidden" name="confirm" value="1">
                        <button type="button" class="btn-outline flex-1" @click="resetDevice = false">Cancel</button>
                        <button type="submit" class="btn-danger flex-1">Reset Device</button>
                    </form>
                </div>
            </div>

            <div x-show="resetPassword" x-cloak class="modal-backdrop flex items-center justify-center p-4" @click.self="resetPassword = false">
                <form method="POST" action="{{ route('admin.stations.reset-password', $station) }}" class="modal-panel">
                    @csrf
                    <div class="mb-4 flex h-10 w-10 items-center justify-center rounded-xl bg-info-100 text-info-700">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                    </div>
                    <h3 class="modal-title">Reset Station Password</h3>
                    <p class="mt-2 text-sm text-muted">The current password cannot be displayed. Enter a new password.</p>
                    <div class="mt-4 space-y-3">
                        <input class="input" type="password" name="password" required minlength="8" placeholder="New password" autocomplete="new-password">
                        <input class="input" type="password" name="password_confirmation" required minlength="8" placeholder="Confirm password" autocomplete="new-password">
                    </div>
                    <div class="modal-actions">
                        <button type="button" class="btn-outline flex-1" @click="resetPassword = false">Cancel</button>
                        <button type="submit" class="btn-primary flex-1">Save Password</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="card-header">
                <h3 class="card-title">Recent Activity</h3>
                <a class="link text-xs" href="{{ route('admin.stations.activity', $station) }}">View all</a>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>When</th><th>Employee</th><th>Action</th><th>Result</th></tr></thead>
                    <tbody>
                        @forelse ($recent as $log)
                            <tr>
                                <td class="whitespace-nowrap text-muted">{{ $log->scanned_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                                <td class="font-medium text-ink">{{ $log->employee?->fullName() ?? '—' }}</td>
                                <td><span class="chip">{{ $log->action }}</span></td>
                                <td>
                                    @php $resultValue = $log->result?->value; @endphp
                                    <span class="{{ $resultValue === 'success' ? 'badge-brand' : 'badge-warn' }}">
                                        <span class="h-1.5 w-1.5 rounded-full bg-current opacity-80"></span>
                                        {{ $log->result?->label() }}{{ $log->failure_reason ? ' · '.$log->failure_reason : '' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-0"><x-empty-state title="No activity" message="This station has not recorded activity yet." icon="device" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
