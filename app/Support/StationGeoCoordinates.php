<?php

namespace App\Support;

use App\Models\AttendanceStation;
use App\Services\StationBindingService;
use Illuminate\Http\Request;

class StationGeoCoordinates
{
    /**
     * @return array{latitude: float, longitude: float}
     */
    public static function validate(Request $request): array
    {
        $validated = $request->validate(
            [
                'latitude' => ['required', 'numeric', 'between:-90,90'],
                'longitude' => ['required', 'numeric', 'between:-180,180'],
            ],
            [
                'latitude.required' => 'Device location is required. Allow location access on the attendance station.',
                'longitude.required' => 'Device location is required. Allow location access on the attendance station.',
            ],
        );

        return [
            'latitude' => (float) $validated['latitude'],
            'longitude' => (float) $validated['longitude'],
        ];
    }

    public static function record(StationBindingService $bindings, AttendanceStation $station, array $coords): void
    {
        $bindings->recordLastKnownLocation($station, $coords['latitude'], $coords['longitude']);
    }
}
