<?php

namespace App\Services;

use App\Enums\BindingStatus;
use App\Enums\StationDeviceStatus;
use App\Models\AttendanceStation;
use App\Models\StationDeviceBinding;
use App\Support\ManilaTime;
use App\Support\SecureHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie as SymfonyCookie;

class StationBindingService
{
    public const COOKIE = 'bacs_station_binding';

    public const IDLE_COOKIE = 'bacs_station_reauth';

    public function parseCookie(?string $value): ?array
    {
        if (! $value) {
            return null;
        }

        $parts = explode('.', $value, 3);
        if (count($parts) !== 3) {
            return null;
        }

        [$stationId, $deviceIdentifier, $bindingToken] = $parts;

        if (! ctype_digit($stationId) || strlen($deviceIdentifier) < 32 || strlen($bindingToken) < 32) {
            return null;
        }

        return [
            'station_id' => (int) $stationId,
            'device_identifier' => $deviceIdentifier,
            'binding_token' => $bindingToken,
        ];
    }

    public function cookiePayload(AttendanceStation $station, string $deviceIdentifier, string $bindingToken): string
    {
        return $station->id.'.'.$deviceIdentifier.'.'.$bindingToken;
    }

    public function makeBindingCookie(string $payload, int $minutes = 60 * 24 * 730): SymfonyCookie
    {
        return cookie(
            self::COOKIE,
            $payload,
            $minutes,
            '/',
            null,
            config('session.secure'),
            true,
            false,
            'lax'
        );
    }

    public function forgetBindingCookie(): SymfonyCookie
    {
        return Cookie::forget(self::COOKIE, '/');
    }

    public function queueIdleReauthCookie(): void
    {
        Cookie::queue(cookie(self::IDLE_COOKIE, '1', 60 * 24, '/', null, config('session.secure'), true, false, 'lax'));
    }

    public function forgetIdleReauthCookie(): SymfonyCookie
    {
        return Cookie::forget(self::IDLE_COOKIE, '/');
    }

    public function secretsFromRequest(Request $request): ?array
    {
        return $this->parseCookie($request->cookie(self::COOKIE));
    }

    public function findActiveBinding(AttendanceStation $station, array $secrets): ?StationDeviceBinding
    {
        if ((int) $secrets['station_id'] !== (int) $station->id) {
            return null;
        }

        $deviceHash = SecureHash::make($secrets['device_identifier']);
        $tokenHash = SecureHash::make($secrets['binding_token']);

        $binding = $station->bindings()->active()->latest('bound_at')->first();

        if (! $binding) {
            return null;
        }

        if (! hash_equals($binding->device_identifier_hash, $deviceHash)) {
            return null;
        }

        if (! hash_equals($binding->binding_token_hash, $tokenHash)) {
            return null;
        }

        return $binding;
    }

    public function bind(AttendanceStation $station): array
    {
        return DB::transaction(function () use ($station) {
            $locked = AttendanceStation::query()->whereKey($station->id)->lockForUpdate()->firstOrFail();

            if ($locked->activeBinding()) {
                throw new \RuntimeException('Station is already bound to a device.');
            }

            $deviceIdentifier = SecureHash::randomToken();
            $bindingToken = SecureHash::randomToken();

            StationDeviceBinding::query()->create([
                'attendance_station_id' => $locked->id,
                'device_identifier_hash' => SecureHash::make($deviceIdentifier),
                'binding_token_hash' => SecureHash::make($bindingToken),
                'bound_at' => ManilaTime::now(),
                'last_seen_at' => ManilaTime::now(),
                'status' => BindingStatus::Active,
            ]);

            $locked->update([
                'device_status' => StationDeviceStatus::Bound,
                'binding_nonce' => Str::random(40),
                'last_seen_at' => ManilaTime::now(),
            ]);

            return [
                'station' => $locked->fresh(),
                'device_identifier' => $deviceIdentifier,
                'binding_token' => $bindingToken,
            ];
        });
    }

    public function unbind(AttendanceStation $station): void
    {
        DB::transaction(function () use ($station) {
            $locked = AttendanceStation::query()->whereKey($station->id)->lockForUpdate()->firstOrFail();

            $locked->bindings()->active()->update([
                'status' => BindingStatus::Unbound,
                'unbound_at' => ManilaTime::now(),
            ]);

            $locked->update([
                'device_status' => StationDeviceStatus::Unbound,
                'binding_nonce' => Str::random(40),
            ]);
        });
    }

    public function touch(AttendanceStation $station, ?StationDeviceBinding $binding = null): void
    {
        $now = ManilaTime::now();
        $station->update(['last_seen_at' => $now]);
        $binding?->update(['last_seen_at' => $now]);
    }
}
