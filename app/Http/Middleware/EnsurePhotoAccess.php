<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhotoAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (auth('station')->check()) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user || ! $user->isActive()) {
            abort(401);
        }

        $path = (string) $request->route('path');

        if (preg_match('#^photos/employees/(\d+)/#', $path, $matches)) {
            $employeeId = (int) $matches[1];

            if (! $user->isManagement() && $user->employee?->id !== $employeeId) {
                abort(403);
            }
        }

        return $next($request);
    }
}
