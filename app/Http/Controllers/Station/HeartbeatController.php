<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class HeartbeatController extends Controller
{
    public function store(Request $request)
    {
        $station = $request->user('station')?->fresh();

        return response()->json([
            'ok' => true,
            'locked' => $station?->isLocked() ?? true,
            'status' => $station?->status?->value,
            'presence' => $station?->presenceLabel(),
            'server_time' => ManilaTime::now()->toIso8601String(),
        ]);
    }
}
