<?php

namespace App\Http\Controllers\Station;

use App\Http\Controllers\Controller;
use App\Services\StationAuthService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function __construct(private readonly StationAuthService $auth) {}

    public function show()
    {
        return view('station.login');
    }

    public function store(Request $request)
    {
        $request->validate([
            'station_id' => ['required', 'string', 'max:64'],
            'password' => ['required', 'string'],
        ]);

        try {
            $result = $this->auth->login($request);
        } catch (ValidationException $e) {
            if (isset($e->errors()['device'])) {
                return back()
                    ->withInput($request->only('station_id'))
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
