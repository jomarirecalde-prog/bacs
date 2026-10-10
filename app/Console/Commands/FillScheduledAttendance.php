<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\AttendanceCalculator;
use App\Services\Payroll\PayrollAttendanceAggregator;
use App\Services\Payroll\PayrollEngine;
use App\Support\ManilaTime;
use Illuminate\Console\Command;

class FillScheduledAttendance extends Command
{
    protected $signature = 'attendance:fill-scheduled
                            {employee_number : e.g. BACS-2026-0002}
                            {from : Start date YYYY-MM-DD}
                            {to : End date YYYY-MM-DD}
                            {--force : Overwrite existing punch data}
                            {--recompute-payroll : Refresh open payroll periods that overlap this range}';

    protected $description = 'Create or update manual DTR rows for each scheduled work day using the employee work schedule times';

    public function handle(AttendanceCalculator $calculator): int
    {
        $employee = Employee::query()
            ->where('employee_number', $this->argument('employee_number'))
            ->first();

        if (! $employee) {
            $this->error('Employee not found.');

            return self::FAILURE;
        }

        $from = ManilaTime::parse($this->argument('from'))->startOfDay();
        $to = ManilaTime::parse($this->argument('to'))->startOfDay();

        if ($to->lt($from)) {
            $this->error('End date must be on or after start date.');

            return self::FAILURE;
        }

        $schedule = $employee->schedule();
        $admin = User::query()->where('username', 'admin')->first();
        $force = (bool) $this->option('force');

        $created = 0;
        $updated = 0;
        $skipped = 0;

        $cursor = $from->copy();
        while ($cursor->lte($to)) {
            $date = $cursor->toDateString();
            $cursor = $cursor->copy()->addDay();

            if (! $schedule->isWorkDay((int) ManilaTime::parse($date)->isoWeekday())) {
                continue;
            }

            $existing = Attendance::query()
                ->where('employee_id', $employee->id)
                ->onDate($date)
                ->first();

            if ($existing && $existing->isRegularComplete() && ! $force) {
                $skipped++;

                continue;
            }

            $punches = $this->punchesForSchedule($date, $schedule);
            $computed = $calculator->calculateFromPunches($date, $punches, $schedule, $employee->id);

            $wasExisting = $existing !== null;

            $record = Attendance::query()->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                ],
                array_merge($computed, $punches, [
                    'status' => $computed['status']->value,
                    'remarks' => $existing?->remarks ?: 'Backfill from work schedule (attendance:fill-scheduled)',
                    'is_manual' => true,
                    'created_by' => $existing?->created_by ?? $admin?->id,
                ])
            );
            $record->syncLegacyFields();
            $record->save();

            if ($wasExisting) {
                $updated++;
            } else {
                $created++;
            }
        }

        $this->info(sprintf(
            '%s (%s): %d created, %d updated, %d skipped (already complete).',
            $employee->fullName(),
            $employee->employee_number,
            $created,
            $updated,
            $skipped
        ));

        if ($this->option('recompute-payroll')) {
            $this->recomputeOverlappingPayroll($employee, $from->toDateString(), $to->toDateString());
        }

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     am_time_in: \Carbon\Carbon,
     *     am_time_out: \Carbon\Carbon,
     *     pm_time_in: \Carbon\Carbon,
     *     pm_time_out: \Carbon\Carbon,
     *     overtime_in: null
     * }
     */
    private function punchesForSchedule(string $date, \App\Models\WorkSchedule $schedule): array
    {
        $start = $this->timeOnDate($date, $schedule->start_time, '08:00:00');
        $breakStart = $this->timeOnDate($date, $schedule->break_start, '12:00:00');
        $breakEnd = $this->timeOnDate($date, $schedule->break_end, '13:00:00');
        $end = $this->timeOnDate($date, $schedule->end_time, '17:00:00');

        return [
            'am_time_in' => $start,
            'am_time_out' => $breakStart,
            'pm_time_in' => $breakEnd,
            'pm_time_out' => $end,
            'overtime_in' => null,
        ];
    }

    private function timeOnDate(string $date, mixed $time, string $fallback): \Carbon\Carbon
    {
        $value = filled($time) ? (string) $time : $fallback;
        if (strlen($value) === 5) {
            $value .= ':00';
        }

        return ManilaTime::combineDateAndTime($date, $value);
    }

    private function recomputeOverlappingPayroll(Employee $employee, string $from, string $to): void
    {
        $periods = PayrollPeriod::query()
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get();

        if ($periods->isEmpty()) {
            $this->line('No payroll periods overlap this range.');

            return;
        }

        $aggregator = app(PayrollAttendanceAggregator::class);
        $engine = app(PayrollEngine::class);

        foreach ($periods as $period) {
            $summary = $aggregator->aggregateEmployee($employee, $period);
            $engine->computeEmployee($period, $employee, $summary, $period->end_date->toDateString());
            $this->line("Recomputed payroll attendance for period: {$period->period_name}");
        }
    }
}
