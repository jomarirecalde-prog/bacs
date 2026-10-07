<?php

namespace App\Providers;

use App\Models\AttendanceCorrectionRequest;
use App\Models\Designation;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Policies\DesignationPolicy;
use App\Policies\PayrollEmployeePolicy;
use App\Models\OfficialTimeRequest;
use App\Models\TravelOrder;
use App\Models\LeaveBalance;
use App\Policies\PayrollPolicy;
use App\Policies\AttendanceCorrectionRequestPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\LeaveApplicationPolicy;
use App\Policies\OfficialTimeRequestPolicy;
use App\Policies\TravelOrderPolicy;
use App\Policies\LeaveBalancePolicy;
use App\Services\DirectoryCatalog;
use App\Services\HolidayResolver;
use App\Services\LeaveResolver;
use App\Services\TravelOrderResolver;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Shared per request so a monthly DTR resolves holidays once, not per day.
        $this->app->singleton(HolidayResolver::class);
        $this->app->singleton(LeaveResolver::class);
        $this->app->singleton(TravelOrderResolver::class);
        $this->app->singleton(DirectoryCatalog::class);
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            $request = request();
            $appPath = parse_url((string) config('app.url'), PHP_URL_PATH) ?: '';

            // Match generated URLs/cookies to the host the user actually opened
            // (localhost vs 127.0.0.1) while keeping the /BACS/public path prefix.
            URL::forceRootUrl(rtrim($request->getSchemeAndHttpHost().$appPath, '/'));
        }

        date_default_timezone_set(config('app.timezone', 'Asia/Manila'));

        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(LeaveApplication::class, LeaveApplicationPolicy::class);
        Gate::policy(TravelOrder::class, TravelOrderPolicy::class);
        Gate::policy(OfficialTimeRequest::class, OfficialTimeRequestPolicy::class);
        Gate::policy(LeaveBalance::class, LeaveBalancePolicy::class);
        Gate::policy(AttendanceCorrectionRequest::class, AttendanceCorrectionRequestPolicy::class);
        Gate::policy(Designation::class, DesignationPolicy::class);
        Gate::policy(PayrollPeriod::class, PayrollPolicy::class);
        Gate::policy(PayrollEmployee::class, PayrollEmployeePolicy::class);

        if (config('database.default') === 'mysql') {
            try {
                DB::statement("SET time_zone = '+08:00'");
            } catch (\Throwable) {
                // Database may be unavailable during package discovery.
            }
        }

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('clock', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('station-login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('station_name')).'|'.$request->ip()));
        RateLimiter::for('station-scan', fn (Request $request) => Limit::perMinute(40)->by($request->user('station')?->id ?: $request->ip()));

        View::composer('layouts.app', function ($view) {
            $user = auth()->user();

            if ($user && ! $user->relationLoaded('employee')) {
                $user->load('employee');
            }

            // Partial navigations keep the existing header, so skip the bell queries.
            if (request()->headers->get('X-BACS-Partial') === '1') {
                $view->with([
                    'unreadNotifications' => 0,
                    'latestNotifications' => collect(),
                    'notificationBell' => [
                        'userId' => $user?->id,
                        'unread' => 0,
                        'items' => [],
                        'feedUrl' => route('notifications.index'),
                        'readAllUrl' => route('notifications.read-all'),
                    ],
                ]);

                return;
            }

            $unread = 0;

            if ($user) {
                try {
                    $unread = app(NotificationService::class)->unreadCount($user);
                } catch (\Throwable) {
                    // Never fail page rendering when notification metadata is unavailable.
                }
            }

            $view->with([
                'unreadNotifications' => $unread,
                'latestNotifications' => collect(),
                'notificationBell' => [
                    'userId' => $user?->id,
                    'unread' => $unread,
                    'items' => [],
                    'feedUrl' => route('notifications.index'),
                    'readAllUrl' => route('notifications.read-all'),
                ],
            ]);
        });
    }
}
