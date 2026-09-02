<?php

use App\Http\Middleware\AppendCsrfTokenHeader;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\EnsureStationDeviceBound;
use App\Http\Middleware\EnsureUserRole;
use App\Http\Middleware\LogRequestPerformance;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['web', 'auth', 'account.active']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('web', LogRequestPerformance::class);
        $middleware->appendToGroup('web', AppendCsrfTokenHeader::class);
        $middleware->alias([
            'role' => EnsureUserRole::class,
            'account.active' => EnsureAccountActive::class,
            'password.changed' => EnsurePasswordChanged::class,
            'station.device' => EnsureStationDeviceBound::class,
        ]);
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('attendance-station') || $request->is('attendance-station/*')) {
                return route('station.login');
            }

            return route('login');
        });
        $middleware->redirectUsersTo(function () {
            if (auth('station')->check() && ! auth('web')->check()) {
                return route('station.dashboard');
            }

            return route('home');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (TokenMismatchException $e, Request $request) {
            if ($request->expectsJson() || $request->ajax() || $request->headers->get('X-BACS-Partial') === '1') {
                return response()->json([
                    'message' => 'Your BACS session has expired. Please sign in again.',
                    'code' => 'SESSION_EXPIRED',
                ], 419);
            }

            return response()->view('errors.419', [], 419);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419) {
                return null;
            }

            if ($request->expectsJson() || $request->ajax() || $request->headers->get('X-BACS-Partial') === '1') {
                return response()->json([
                    'message' => 'Your BACS session has expired. Please sign in again.',
                    'code' => 'SESSION_EXPIRED',
                ], 419);
            }

            return response()->view('errors.419', [], 419);
        });
    })->create();
