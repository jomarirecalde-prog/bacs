<?php

namespace App\Http\Controllers\Station;

use App\Enums\StationStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceStation;
use App\Services\StationAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private readonly StationAuthService $auth) {}

    public function show()
    {
        $stationNames = AttendanceStation::query()
            ->where('status', '!=', StationStatus::Inactive)
            ->orderBy('station_name')
            ->pluck('station_name')
            ->all();

        return view('station.login', [
            'stationNames' => $stationNames,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'station_name' => ['required', 'string', 'max:120'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->auth->login($request);
        } catch (ValidationException $e) {
            if (isset($e->errors()['device'])) {
                return back()
                    ->withInput($request->only('station_name'))
                    ->with('device_conflict', true)
                    ->withErrors($e->errors());
            }

            throw $e;
        }

        $redirect = redirect()->route('station.dashboard');
        foreach ($result['cookies'] as $cookie) {
            $redirect->withCookie($cookie);
        }

        return $redirect;
    }

    public function destroy(Request $request)
    {
        $this->auth->logout($request);

        return redirect()->route('station.login')->with('success', 'Station logged out. Device binding was not removed.');
    }
}
