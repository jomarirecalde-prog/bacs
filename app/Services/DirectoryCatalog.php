<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Cached lookup lists for filter dropdowns. Attendance itself is never cached.
 */
class DirectoryCatalog
{
    private const DEPARTMENTS_KEY = 'catalog:departments';

    private const EMPLOYEES_KEY = 'catalog:employees';

    private const TTL = 600;

    public function departments(): Collection
    {
        return Cache::remember(self::DEPARTMENTS_KEY, self::TTL, function () {
            return Department::query()->active()->ordered()->get(['id', 'name', 'sort_order', 'status']);
        });
    }

    public function employeeOptions(?int $departmentId = null): Collection
    {
        $all = Cache::remember(self::EMPLOYEES_KEY, self::TTL, function () {
            return Employee::query()
                ->orderBy('last_name')
                ->orderBy('first_name')
                ->get(['id', 'employee_number', 'first_name', 'last_name', 'full_name', 'department_id']);
        });

        if ($departmentId) {
            return $all->where('department_id', $departmentId)->values();
        }

        return $all;
    }

    public static function flush(): void
    {
        Cache::forget(self::DEPARTMENTS_KEY);
        Cache::forget(self::EMPLOYEES_KEY);
    }
}
