<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\LeaveBalanceService;
use App\Support\ManilaTime;
use Illuminate\Console\Command;

class InitializeLeaveBalances extends Command
{
    protected $signature = 'leave:initialize-balances {--year=} {--employee=}';

    protected $description = 'Initialize per-employee leave balances from the company default policy';

    public function handle(LeaveBalanceService $balances): int
    {
        $year = (int) ($this->option('year') ?: ManilaTime::now()->year);
        $employeeId = $this->option('employee');

        $query = Employee::query()->orderBy('id');
        if ($employeeId) {
            $query->whereKey($employeeId);
        }

        $count = 0;
        $query->each(function (Employee $employee) use ($balances, $year, &$count) {
            $balances->initializeForEmployee($employee, $year);
            $count++;
        });

        $this->info("Initialized leave balances for {$count} employee(s) in {$year}.");

        return self::SUCCESS;
    }
}
