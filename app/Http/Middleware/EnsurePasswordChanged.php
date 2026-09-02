<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->must_change_password && ! $request->routeIs(
            'profile.password',
            'profile.password.update',
            'logout',
            'server-time',
            'session.heartbeat',
            'session.extend',
        )) {
            return redirect()
                ->route('profile.password')
                ->with('error', 'Please change your temporary password before continuing.');
        }

        return $next($request);
    }
}
