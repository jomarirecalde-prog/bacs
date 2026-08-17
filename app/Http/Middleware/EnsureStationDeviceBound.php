<?php

namespace App\Http\Middleware;

use App\Enums\StationStatus;
use App\Services\StationAuthService;
use App\Services\StationBindingService;
use App\Support\ManilaTime;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStationDeviceBound
{
    public function __construct(
        private readonly StationAuthService $auth,
        private readonly StationBindingService $bindings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $station = $request->user('station');

        if (! $station) {
            $station = $this->auth->restoreFromBinding($request);
        } else {
            $station = $station->fresh();
            if ($station) {
                auth()->guard('station')->setUser($station);
            }
        }

        if (! $station) {
            return $this->deny($request, 'Please log in to the attendance station.');
        }

        if ($station->status === StationStatus::Inactive) {
            $this->auth->logout($request);

            return $this->deny($request, 'This attendance station is inactive.');
        }

        $sessionNonce = $request->session()->get('station_binding_nonce');
        if ($sessionNonce && $station->binding_nonce && ! hash_equals((string) $station->binding_nonce, (string) $sessionNonce)) {
            $this->auth->logout($request);

            return $this->deny($request, 'This attendance station was reset. Please log in again after the Super Admin rebinds the device.');
        }

        $binding = $this->auth->currentBinding($request, $station);
        if (! $binding) {
            $this->auth->logout($request);

            return $this->deny($request, 'This attendance station is already registered to another device.', true);
        }

        $idleMinutes = (int) $station->idle_timeout_minutes;
        $lastActivity = (int) $request->session()->get('station_last_activity', 0);
        if ($idleMinutes > 0 && $lastActivity > 0) {
            $idleFor = ManilaTime::now()->timestamp - $lastActivity;
            if ($idleFor >= ($idleMinutes * 60)) {
                $this->auth->logout($request, idle: true);

                return $this->deny($request, 'Station session expired due to inactivity. Device binding was not removed.');
            }
        }

        if (! $request->is('attendance-station/heartbeat')) {
            $request->session()->put('station_last_activity', ManilaTime::now()->timestamp);
        }

        $this->bindings->touch($station, $binding);
        $request->attributes->set('station_binding', $binding);
        $request->attributes->set('station_locked', $station->isLocked());

        return $next($request);
    }

    private function deny(Request $request, string $message, bool $deviceConflict = false): Response
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => false,
                'code' => $deviceConflict ? 'DEVICE_NOT_AUTHORIZED' : 'STATION_NOT_AUTHORIZED',
                'title' => $deviceConflict ? 'Station Already Registered' : 'Station Not Authorized',
                'message' => $message,
            ], 401);
        }

        return redirect()->route('station.login')->with('error', $message);
    }
}
