<?php

namespace App\Services;

use App\Enums\StationActivityResult;
use App\Enums\StationStatus;
use App\Models\AttendanceStation;
use App\Models\StationDeviceBinding;
use App\Support\ManilaTime;
use App\Support\SecureHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class StationAuthService
{
    public function __construct(
        private readonly StationBindingService $bindings,
        private readonly StationActivityLogger $activity,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function login(Request $request): array
    {
        $stationCode = strtoupper(trim((string) $request->input('station_id')));
        $password = (string) $request->input('password');
        $rateKey = $this->rateKey($stationCode, $request);

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            throw ValidationException::withMessages([
                'station_id' => 'Too many login attempts. Please try again shortly.',
            ]);
        }

        $station = AttendanceStation::query()->where('station_code', $stationCode)->first();

        if (! $station || ! Hash::check($password, $station->password)) {
            RateLimiter::hit($rateKey, 60);
            $this->recordFailedAttempt($station, $request, 'invalid_credentials');

            throw ValidationException::withMessages([
                'station_id' => 'These station credentials do not match our records.',
            ]);
        }

        if ($station->isLoginTemporarilyLocked()) {
            RateLimiter::hit($rateKey, 60);
            $this->activity->log($station, 'login', StationActivityResult::Failure, $request, failureReason: 'temporarily_locked');

            throw ValidationException::withMessages([
                'station_id' => 'Station login is temporarily locked because of too many failed attempts. Please try again later.',
            ]);
        }

        if ($station->status === StationStatus::Inactive) {
            RateLimiter::hit($rateKey, 60);
            $this->activity->log($station, 'login', StationActivityResult::Failure, $request, failureReason: 'inactive');

            throw ValidationException::withMessages([
                'station_id' => 'This attendance station is inactive. Please contact the Super Admin.',
            ]);
        }

        $secrets = $this->bindings->secretsFromRequest($request);
        $activeBinding = $station->activeBinding();
        $cookies = [];

        if ($activeBinding) {
            if (! $secrets || ! $this->bindings->findActiveBinding($station, $secrets)) {
                RateLimiter::hit($rateKey, 60);
                $this->activity->log($station, 'login', StationActivityResult::Failure, $request, failureReason: 'device_not_authorized');

                throw ValidationException::withMessages([
                    'device' => 'This attendance station is already registered to another device.',
                ])->errorBag('device');
            }

            $this->bindings->touch($station, $activeBinding);
            $payload = $this->bindings->cookiePayload($station, $secrets['device_identifier'], $secrets['binding_token']);
            $cookies[] = $this->bindings->makeBindingCookie($payload);
        } else {
            $bound = $this->bindings->bind($station);
            $station = $bound['station'];
            $payload = $this->bindings->cookiePayload($station, $bound['device_identifier'], $bound['binding_token']);
            $cookies[] = $this->bindings->makeBindingCookie($payload);
            $this->auditLogger->log(null, 'station_device_bound', 'Attendance Stations', $station->id, "Station {$station->station_code} was bound to this device.");
        }

        RateLimiter::clear($rateKey);
        $station->update([
            'failed_login_attempts' => 0,
            'login_locked_until' => null,
        ]);

        Auth::guard('station')->login($station, true);
        $request->session()->regenerate();
        $request->session()->put('station_last_activity', ManilaTime::now()->timestamp);
        $request->session()->put('station_binding_nonce', $station->fresh()->binding_nonce);

        $cookies[] = $this->bindings->forgetIdleReauthCookie();

        $this->activity->log($station, 'login', StationActivityResult::Success, $request, deviceHash: $this->deviceHashFromSecrets($this->bindings->parseCookie($payload)));
        $this->auditLogger->log(null, 'station_login', 'Attendance Stations', $station->id, "Station {$station->station_code} logged in.");

        return ['station' => $station->fresh(), 'cookies' => $cookies];
    }

    public function logout(Request $request, bool $idle = false): void
    {
        $station = $request->user('station');

        if ($station) {
            $this->activity->log($station, $idle ? 'idle_logout' : 'logout', StationActivityResult::Success, $request);
            $this->auditLogger->log(null, $idle ? 'station_idle_logout' : 'station_logout', 'Attendance Stations', $station->id, "Station {$station->station_code} logged out.");
        }

        Auth::guard('station')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($idle) {
            $this->bindings->queueIdleReauthCookie();
        }
    }

    public function restoreFromBinding(Request $request): ?AttendanceStation
    {
        if ($request->cookie(StationBindingService::IDLE_COOKIE)) {
            return null;
        }

        $secrets = $this->bindings->secretsFromRequest($request);
        if (! $secrets) {
            return null;
        }

        $station = AttendanceStation::query()->find($secrets['station_id']);
        if (! $station || $station->status === StationStatus::Inactive) {
            return null;
        }

        $binding = $this->bindings->findActiveBinding($station, $secrets);
        if (! $binding) {
            return null;
        }

        Auth::guard('station')->login($station, true);
        $request->session()->put('station_last_activity', ManilaTime::now()->timestamp);
        $request->session()->put('station_binding_nonce', $station->binding_nonce);
        $this->bindings->touch($station, $binding);

        return $station;
    }

    public function currentBinding(Request $request, AttendanceStation $station): ?StationDeviceBinding
    {
        $secrets = $this->bindings->secretsFromRequest($request);

        return $secrets ? $this->bindings->findActiveBinding($station, $secrets) : null;
    }

    private function recordFailedAttempt(?AttendanceStation $station, Request $request, string $reason): void
    {
        if ($station) {
            $attempts = $station->failed_login_attempts + 1;
            $payload = ['failed_login_attempts' => $attempts];
            if ($attempts >= 5) {
                $payload['login_locked_until'] = ManilaTime::now()->addMinutes(15);
            }
            $station->update($payload);
        }

        $this->activity->log($station, 'login', StationActivityResult::Failure, $request, failureReason: $reason);
        $this->auditLogger->log(null, 'station_login_failed', 'Attendance Stations', $station?->id, 'Failed attendance station login.');
    }

    private function rateKey(string $stationCode, Request $request): string
    {
        return 'station-login:'.strtolower($stationCode).'|'.$request->ip();
    }

    private function deviceHashFromSecrets(?array $secrets): ?string
    {
        return $secrets ? SecureHash::make($secrets['device_identifier']) : null;
    }
}
