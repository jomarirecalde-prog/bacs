<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SessionController extends Controller
{
    /**
     * Lightweight session heartbeat for authenticated web users.
     * Touches the session (refreshes idle timeout) without extra DB queries.
     */
    public function heartbeat(Request $request)
    {
        $lifetimeMinutes = (int) config('session.lifetime', 120);

        return response()->json([
            'ok' => true,
            'authenticated' => true,
            'csrf_token' => csrf_token(),
            'expires_at' => now()->addMinutes($lifetimeMinutes)->timestamp,
            'lifetime_minutes' => $lifetimeMinutes,
            'warn_before_minutes' => (int) config('session.warn_before_expiry', 5),
        ]);
    }

    /**
     * Explicit session extension when the user confirms they want to stay signed in.
     */
    public function extend(Request $request)
    {
        return $this->heartbeat($request);
    }

}
