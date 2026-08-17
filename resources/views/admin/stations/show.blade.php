@extends('layouts.app')

@section('title', $station->station_code)
@section('page-title', $station->station_name)
@section('page-subtitle', $station->station_code.' · '.$station->location)

@section('content')
<div class="grid gap-6 lg:grid-cols-3">
    <div class="card p-6 space-y-3 text-sm">
        <div class="flex justify-between gap-4"><span class="text-slate-500">Station ID</span><span class="font-semibold">{{ $station->station_code }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Location</span><span class="text-right">{{ $station->location }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Status</span><span>{{ $station->status->label() }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Device</span><span>{{ $station->device_status->label() }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Presence</span><span>{{ $station->presenceLabel() }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Idle timeout</span><span>{{ $station->idleTimeoutLabel() }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Last seen</span><span>{{ $station->last_seen_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Last scan</span><span>{{ $station->last_scan_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</span></div>
        <div class="flex justify-between gap-4"><span class="text-slate-500">Bound at</span><span>{{ $binding?->bound_at?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—' }}</span></div>
        <p class="text-xs text-slate-500">{{ $station->description ?: 'No description.' }}</p>
        <div class="flex flex-wrap gap-2 pt-2">
            <a class="btn-secondary" href="{{ route('admin.stations.edit', $station) }}">Edit</a>
            <a class="btn-secondary" href="{{ route('admin.stations.activity', $station) }}">View Activity</a>
            <a class="btn-secondary" href="{{ route('admin.stations.attendance', $station) }}">View Attendance</a>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($station->isInactive())
                <form method="POST" action="{{ route('admin.stations.activate', $station) }}">@csrf<button class="btn-primary">Activate</button></form>
            @else
                <form method="POST" action="{{ route('admin.stations.deactivate', $station) }}" onsubmit="return confirm('Deactivate this station?')">@csrf<button class="btn-secondary">Deactivate</button></form>
            @endif
            @if ($station->isLocked())
                <form method="POST" action="{{ route('admin.stations.unlock', $station) }}">@csrf<button class="btn-primary">Unlock Station</button></form>
            @else
                <form method="POST" action="{{ route('admin.stations.lock', $station) }}" onsubmit="return confirm('Lock this station? The scanner will stop recording attendance.')">@csrf<button class="btn-danger">Lock Station</button></form>
            @endif
        </div>
    </div>

    <div class="lg:col-span-2 space-y-6">
        <div class="card p-6" x-data="{ resetDevice: false, resetPassword: false }">
            <h3 class="font-bold">Device Binding</h3>
            <p class="mt-1 text-sm text-slate-500">Once bound, this Station ID can only operate from the registered device until you reset it.</p>
            <div class="mt-4 rounded-2xl border border-slate-200 p-4 text-sm">
                <div>Current Device: <strong>{{ $station->device_status->label() }}</strong></div>
                <div class="text-slate-500">Current Location: {{ $station->location }}</div>
            </div>
            @if ($station->isBound())
                <button class="btn-danger mt-4" type="button" @click="resetDevice = true">Reset / Transfer Device</button>
            @else
                <p class="mt-4 text-sm text-slate-500">This station is unbound. The next successful login will bind it to that device.</p>
            @endif

            <div x-show="resetDevice" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <div class="card max-w-md p-6">
                    <h3 class="text-lg font-bold">Reset Station Device?</h3>
                    <p class="mt-2 text-sm text-slate-600">Station: <strong>{{ $station->station_code }}</strong></p>
                    <p class="text-sm text-slate-600">Current Device: Bound</p>
                    <p class="text-sm text-slate-600">Current Location: {{ $station->location }}</p>
                    <p class="mt-3 text-sm text-slate-500">Resetting this device will allow the station to be registered on another device. The current device will no longer be authorized.</p>
                    <form method="POST" action="{{ route('admin.stations.unbind', $station) }}" class="mt-5 flex gap-2">
                        @csrf
                        <input type="hidden" name="confirm" value="1">
                        <button type="button" class="btn-secondary flex-1" @click="resetDevice = false">Cancel</button>
                        <button class="btn-danger flex-1">Reset Device</button>
                    </form>
                </div>
            </div>

            <h3 class="mt-8 font-bold">Reset Password</h3>
            <button class="btn-secondary mt-3" type="button" @click="resetPassword = true">Reset Password</button>
            <div x-show="resetPassword" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                <form method="POST" action="{{ route('admin.stations.reset-password', $station) }}" class="card max-w-md p-6 w-full">
                    @csrf
                    <h3 class="text-lg font-bold">Reset Station Password</h3>
                    <p class="mt-2 text-sm text-slate-500">The current password cannot be displayed. Enter a new password.</p>
                    <input class="input mt-4" type="password" name="password" required minlength="8" placeholder="New password">
                    <input class="input mt-3" type="password" name="password_confirmation" required minlength="8" placeholder="Confirm password">
                    <div class="mt-5 flex gap-2">
                        <button type="button" class="btn-secondary flex-1" @click="resetPassword = false">Cancel</button>
                        <button class="btn-primary flex-1">Save Password</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card overflow-hidden">
            <div class="px-5 py-4 border-b font-bold">Recent Activity</div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>When</th><th>Employee</th><th>Action</th><th>Result</th></tr></thead>
                    <tbody>
                        @forelse ($recent as $log)
                            <tr>
                                <td>{{ $log->scanned_at?->timezone('Asia/Manila')->format('M j, g:i A') }}</td>
                                <td>{{ $log->employee?->fullName() ?? '—' }}</td>
                                <td>{{ $log->action }}</td>
                                <td>{{ $log->result?->label() }}{{ $log->failure_reason ? ' · '.$log->failure_reason : '' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-empty-state title="No activity" message="This station has not recorded activity yet." /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
