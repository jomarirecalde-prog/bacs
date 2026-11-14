<?php

namespace App\Support;

use App\Enums\ApprovalTransactionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Resolves a sensible back URL for the current route when a view does not set @section('back-url').
 */
final class PageBackTarget
{
    private const ROOT_ROUTES = [
        'admin.dashboard',
        'employee.dashboard',
        'admin.payroll.dashboard',
        'home',
    ];

    public static function isRootRoute(?string $name): bool
    {
        return is_string($name) && $name !== '' && in_array($name, self::ROOT_ROUTES, true);
    }

    /**
     * @return array{url: ?string, label: string}
     */
    public static function forRequest(?Request $request = null): array
    {
        $request ??= request();
        $route = $request->route();

        if ($route === null) {
            return ['url' => null, 'label' => 'Back'];
        }

        $name = $route->getName();
        if (! is_string($name) || $name === '') {
            return ['url' => null, 'label' => 'Back'];
        }

        if (in_array($name, self::ROOT_ROUTES, true)) {
            return ['url' => null, 'label' => 'Back'];
        }

        $parameters = $route->parameters();

        $custom = self::customTarget($name, $parameters);
        if ($custom !== null) {
            return $custom;
        }

        $parentName = self::parentRouteName($name);
        if ($parentName === null) {
            return ['url' => null, 'label' => 'Back'];
        }

        $url = self::urlForRoute($parentName, $parameters);
        if ($url === null) {
            return ['url' => null, 'label' => 'Back'];
        }

        return [
            'url' => $url,
            'label' => self::labelForRoute($parentName),
        ];
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{url: string, label: string}|null
     */
    private static function customTarget(string $name, array $parameters): ?array
    {
        return match ($name) {
            'admin.settings.admins.edit' => self::fixed('admin.settings.index', 'Back to settings'),
            'admin.settings.approval-workflow.history' => self::fixed('admin.settings.index', 'Back to settings', '#approval-workflow'),
            'admin.settings.approval-workflow.reassignments' => self::settingsWorkflowBack($parameters),
            'admin.leave.entitlements.edit' => self::fixed('admin.leave.entitlements', 'Back to list'),
            'admin.leave.entitlements.show' => self::fixed('admin.leave.entitlements', 'Back to list'),
            'admin.leave.entitlements.adjustments' => self::withModel('admin.leave.entitlements.show', $parameters, ['employee'], 'Back'),
            'admin.leave.entitlements.leave-history' => self::withModel('admin.leave.entitlements.show', $parameters, ['employee'], 'Back'),
            'admin.leave.policy' => self::fixed('admin.leave.entitlements', 'Back to employee balances'),
            'admin.leave.workflow.history' => self::withModel('admin.leave.workflow.show', $parameters, ['department'], 'Back to configuration'),
            'admin.leave.workflow.show' => self::fixed('admin.settings.index', 'Back to settings', '#approval-workflow'),
            'admin.employees.qr', 'admin.employees.qr.print' => self::withModel('admin.employees.show', $parameters, ['employee'], 'Back to employee'),
            'admin.stations.locations', 'admin.stations.monitoring' => self::fixed('admin.stations.index', 'Back to stations'),
            'admin.stations.activity', 'admin.stations.attendance' => self::withModel('admin.stations.show', $parameters, ['station'], 'Back to station'),
            'admin.payroll.settings.index' => self::fixed('admin.payroll.dashboard', 'Back to payroll dashboard'),
            'profile.password' => self::fixed('profile.show', 'Back to profile'),
            'employee.leave.balances.adjustments' => self::urlFromRoute(
                'employee.leave.balances',
                ['year' => $parameters['year'] ?? request()->query('year', now()->year)],
                'Back to balances',
            ),
            'employee.dtr.show', 'employee.dtr.export', 'employee.dtr.print' => self::fixed('employee.dtr', 'Back to DTR'),
            'admin.email-notifications.preview' => self::fixed('admin.settings.index', 'Back to settings', '#email-notifications'),
            default => null,
        };
    }

    /**
     * @return array{url: string, label: string}|null
     */
    private static function settingsWorkflowBack(array $parameters): ?array
    {
        $type = $parameters['transactionType'] ?? null;
        if ($type instanceof ApprovalTransactionType) {
            $isPardon = $type === ApprovalTransactionType::Pardon;
        } else {
            $isPardon = (string) $type === ApprovalTransactionType::Pardon->value;
        }
        $fragment = $isPardon ? '#pardon-approval-workflow' : '#approval-workflow';

        return self::fixed('admin.settings.index', 'Back to workflow settings', $fragment);
    }

    /**
     * @return array{url: string, label: string}
     */
    private static function fixed(string $route, string $label, ?string $fragment = null): array
    {
        return self::urlFromRoute($route, [], $label, $fragment);
    }

    /**
     * @param  list<string>  $keys
     * @return array{url: string, label: string}|null
     */
    private static function withModel(string $route, array $parameters, array $keys, string $label): ?array
    {
        $params = [];
        foreach ($keys as $key) {
            if (! array_key_exists($key, $parameters)) {
                return null;
            }
            $params[$key] = $parameters[$key];
        }

        return self::urlFromRoute($route, $params, $label);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array{url: string, label: string}
     */
    private static function urlFromRoute(string $route, array $params, string $label, ?string $fragment = null): array
    {
        $url = self::urlForRoute($route, $params);

        if ($url === null) {
            return ['url' => null, 'label' => $label];
        }

        if ($fragment) {
            $url .= str_starts_with($fragment, '#') ? $fragment : '#'.$fragment;
        }

        return ['url' => $url, 'label' => $label];
    }

    private static function parentRouteName(string $name): ?string
    {
        if (self::isListRoute($name)) {
            return null;
        }

        $parts = explode('.', $name);
        $last = array_pop($parts);

        $map = [
            'edit' => 'show',
            'create' => 'index',
            'show' => 'index',
            'apply' => 'index',
            'form' => 'index',
            'pdf' => 'show',
            'print' => 'show',
            'preview' => 'show',
            'attachment' => 'show',
            'export' => 'index',
        ];

        if (isset($map[$last])) {
            $parts[] = $map[$last];
            $candidate = implode('.', $parts);
            if (Route::has($candidate)) {
                return $candidate;
            }

            if ($map[$last] === 'index') {
                return self::indexFallback($parts);
            }
        }

        if (count($parts) >= 2) {
            $parent = implode('.', $parts);
            if (Route::has($parent)) {
                return $parent;
            }

            $indexCandidate = $parent.'.index';
            if (Route::has($indexCandidate)) {
                return $indexCandidate;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $parts
     */
    private static function indexFallback(array $parts): ?string
    {
        while (count($parts) > 0) {
            $candidate = implode('.', $parts).'.index';
            if (Route::has($candidate)) {
                return $candidate;
            }
            array_pop($parts);
        }

        return null;
    }

    private static function isListRoute(string $name): bool
    {
        if (in_array($name, self::ROOT_ROUTES, true)) {
            return true;
        }

        if (Str::endsWith($name, '.index')) {
            return true;
        }

        return in_array($name, [
            'employee.attendance',
            'employee.dtr',
            'employee.calendar',
            'employee.qr',
            'admin.leave.entitlements',
            'admin.settings.index',
            'admin.dtr.index',
            'admin.dtr.monthly',
            'admin.calendar.index',
            'profile.show',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private static function urlForRoute(string $routeName, array $parameters): ?string
    {
        if (! Route::has($routeName)) {
            return null;
        }

        $route = Route::getRoutes()->getByName($routeName);
        if ($route === null) {
            return null;
        }

        $needed = $route->parameterNames();
        $bound = [];
        foreach ($needed as $key) {
            if (array_key_exists($key, $parameters)) {
                $bound[$key] = $parameters[$key];
            }
        }

        try {
            return route($routeName, $bound);
        } catch (\Throwable) {
            if ($bound !== []) {
                return null;
            }

            try {
                return route($routeName);
            } catch (\Throwable) {
                return null;
            }
        }
    }

    private static function labelForRoute(string $routeName): string
    {
        if (Str::contains($routeName, 'settings')) {
            return 'Back to settings';
        }

        if (Str::endsWith($routeName, '.dashboard')) {
            return 'Back to dashboard';
        }

        if (Str::endsWith($routeName, '.index') || $routeName === 'admin.leave.entitlements') {
            return 'Back to list';
        }

        if (Str::endsWith($routeName, '.show')) {
            return 'Back';
        }

        return 'Back';
    }
}