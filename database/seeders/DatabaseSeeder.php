<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Models\Holiday;
use App\Models\Setting;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put('company_name', 'BACS CONSTRUCTION AND DEVELOPMENT CORPORATION');
        Setting::put('company_address', 'Puerto Princesa City, Palawan, Philippines');
        Setting::put('dtr_year', '2026');

        WorkSchedule::query()->updateOrCreate(
            ['name' => 'Regular Shift'],
            [
                'start_time' => '08:00:00',
                'end_time' => '17:00:00',
                'grace_period_minutes' => 10,
                'break_start' => '12:00:00',
                'break_end' => '13:00:00',
                'required_minutes' => 480,
                'work_days' => [1, 2, 3, 4, 5],
                'is_default' => true,
                'status' => AccountStatus::Active,
            ]
        );

        $holiday = Holiday::query()->whereDate('holiday_date', '2026-06-12')->first();
        if ($holiday) {
            $holiday->update(['name' => 'Independence Day', 'type' => 'regular']);
        } else {
            Holiday::query()->create([
                'name' => 'Independence Day',
                'holiday_date' => '2026-06-12',
                'type' => 'regular',
            ]);
        }

        $this->call([
            DepartmentSeeder::class,
            UserSeeder::class,
            EmployeeSeeder::class,
        ]);

        $this->deactivateLegacySampleData();
    }

    private function deactivateLegacySampleData(): void
    {
        $legacyNumbers = ['SUP-0001', 'EMP-0001', 'EMP-0002', 'EMP-0003', 'EMP-0004', 'EMP-0005', 'EMP-0006'];

        \App\Models\Employee::query()
            ->whereIn('employee_number', $legacyNumbers)
            ->with('user')
            ->get()
            ->each(function (\App\Models\Employee $employee) {
                $employee->user?->update(['status' => AccountStatus::Inactive]);
            });

        \App\Models\Department::query()
            ->whereIn('name', [
                'IT Department',
                'HR Department',
                'Finance Department',
                'Administration',
                'Operations',
            ])
            ->update(['status' => AccountStatus::Inactive]);
    }
}
