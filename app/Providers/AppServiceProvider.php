<?php

namespace App\Providers;

use App\Models\AppNotification;
use App\Services\NotificationService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone', 'Asia/Manila'));

        try {
            DB::statement("SET time_zone = '+08:00'");
        } catch (\Throwable) {
            // Database may be unavailable during package discovery.
        }

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('clock', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('station-login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('station_id')).'|'.$request->ip()));
        RateLimiter::for('station-scan', fn (Request $request) => Limit::perMinute(40)->by($request->user('station')?->id ?: $request->ip()));

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();
            $unread = 0;
            $latest = collect();

            if ($user) {
                $unread = app(NotificationService::class)->unreadCount($user);
                $latest = AppNotification::query()
                    ->where('user_id', $user->id)
                    ->latest()
                    ->limit(8)
                    ->get();
            }

            $view->with([
                'unreadNotifications' => $unread,
                'latestNotifications' => $latest,
            ]);
        });
    }
}
