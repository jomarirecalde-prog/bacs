<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Support\ManilaTime;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $station = $request->user('station');

        return view('station.dashboard', [
            'station' => $station,
            'now' => ManilaTime::now(),
        ]);
    }
}
