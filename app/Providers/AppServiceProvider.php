<?php

namespace App\Providers;

use App\Services\HolidayResolver;
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
        if (($_ENV['VERCEL'] ?? getenv('VERCEL')) === '1') {
            $this->app->useStoragePath('/tmp/storage');
        }

        // Shared per request so a monthly DTR resolves holidays once, not per day.
        $this->app->singleton(HolidayResolver::class);
    }

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone', 'Asia/Manila'));

        try {
            if (DB::connection()->getDriverName() === 'mysql') {
                DB::statement("SET time_zone = '+08:00'");
            }
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
                $latest = app(NotificationService::class)->latest($user, 8);
            }

            $view->with([
                'unreadNotifications' => $unread,
                'latestNotifications' => $latest,
                'notificationBell' => [
                    'userId' => $user?->id,
                    'unread' => $unread,
                    'items' => $latest->map->toBellArray()->values(),
                    'feedUrl' => route('notifications.index'),
                    'readAllUrl' => route('notifications.read-all'),
                ],
            ]);
        });
    }
}
