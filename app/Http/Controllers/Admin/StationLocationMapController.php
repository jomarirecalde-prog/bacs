<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AttendanceStation;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class StationLocationMapController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', AttendanceStation::class);

        return view('admin.stations.location-map', [
            'stations' => $this->stationsPayload(),
            'locationsUrl' => route('admin.stations.locations.data'),
            'mapboxToken' => $this->mapboxAccessToken(),
            'mapStyle' => $this->mapboxStyle(),
        ]);
    }

    public function data(Request $request)
    {
        $this->authorize('viewAny', AttendanceStation::class);

        return response()->json([
            'stations' => $this->stationsPayload(),
            'server_time' => ManilaTime::now()->toIso8601String(),
        ]);
    }

    private function mapboxAccessToken(): ?string
    {
        $fromConfig = config('services.mapbox.access_token');
        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return trim($fromConfig);
        }

        $fromEnv = $_ENV['MAPBOX_ACCESS_TOKEN'] ?? getenv('MAPBOX_ACCESS_TOKEN');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        return null;
    }

    private function mapboxStyle(): string
    {
        $fromConfig = config('services.mapbox.style');
        if (is_string($fromConfig) && trim($fromConfig) !== '') {
            return trim($fromConfig);
        }

        $fromEnv = $_ENV['MAPBOX_STYLE'] ?? getenv('MAPBOX_STYLE');
        if (is_string($fromEnv) && trim($fromEnv) !== '') {
            return trim($fromEnv);
        }

        return 'mapbox://styles/mapbox/streets-v12';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stationsPayload(): array
    {
        return AttendanceStation::query()
            ->orderBy('station_code')
            ->get()
            ->map(fn (AttendanceStation $station) => [
                'id' => $station->id,
                'code' => $station->station_code,
                'name' => $station->station_name,
                'address' => $station->location,
                'presence' => $station->presenceLabel(),
                'online' => $station->isOnline(),
                'latitude' => $station->last_latitude !== null ? (float) $station->last_latitude : null,
                'longitude' => $station->last_longitude !== null ? (float) $station->last_longitude : null,
                'located_at' => $station->last_location_at?->timezone('Asia/Manila')->toIso8601String(),
                'show_url' => route('admin.stations.show', $station),
            ])
            ->values()
            ->all();
    }
}
