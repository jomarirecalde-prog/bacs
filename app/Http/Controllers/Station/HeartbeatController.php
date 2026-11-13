<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Services\StationBindingService;
use App\Support\ManilaTime;
use App\Support\StationGeoCoordinates;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function __construct(
        private readonly StationBindingService $bindings,
    ) {}

    public function store(Request $request)
    {
        $station = $request->user('station')?->fresh();

        if ($station) {
            $coords = StationGeoCoordinates::validate($request);
            StationGeoCoordinates::record($this->bindings, $station, $coords);
            $station = $station->fresh();
        }

        return response()->json([
            'ok' => true,
            'locked' => $station?->isLocked() ?? true,
            'status' => $station?->status?->value,
            'presence' => $station?->presenceLabel(),
            'server_time' => ManilaTime::now()->toIso8601String(),
        ]);
    }
}
