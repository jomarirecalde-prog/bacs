<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Enums\PayrollWorkdayClassification;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\WorkSchedule;
use App\Services\HolidayResolver;
use App\Support\ManilaTime;

class OvertimeWorkdayClassifier
{
    public function __construct(private readonly HolidayResolver $holidays) {}

    /**
     * @return array{
     *     classification: PayrollWorkdayClassification,
     *     is_rest_day: bool,
     *     classification_label: string,
     *     rest_day_label: string
     * }
     */
    public function classify(string $date, Employee $employee, Attendance $record, WorkSchedule $schedule): array
    {
        $day = ManilaTime::parse($date);
        $isScheduledWorkDay = $schedule->isWorkDay((int) $day->isoWeekday());
        $isRestDay = $record->status === AttendanceStatus::RestDay
            || (! $isScheduledWorkDay && ! $this->hasHoliday($date, $employee));

        $classification = $this->holidayClassification($date, $employee);

        return [
            'classification' => $classification,
            'is_rest_day' => $isRestDay,
            'classification_label' => $classification->label(),
            'rest_day_label' => $isRestDay ? 'Rest day' : 'Regular workday',
        ];
    }

    public function holidayClassification(string $date, Employee $employee): PayrollWorkdayClassification
    {
        $legacy = Holiday::query()->where('holiday_date', $date)->get();
        $regularCount = $legacy->where('type', 'regular')->count();
        $specialCount = $legacy->filter(fn ($row) => $row->type !== 'regular')->count();

        if ($regularCount >= 2 || ($regularCount >= 1 && $specialCount >= 1)) {
            return PayrollWorkdayClassification::DoubleHoliday;
        }

        if ($specialCount >= 2) {
            return PayrollWorkdayClassification::DoubleSpecialNonWorking;
        }

        if ($regularCount >= 1) {
            return PayrollWorkdayClassification::RegularHoliday;
        }

        if ($specialCount >= 1 || $this->holidays->forDate($date, $employee)) {
            return PayrollWorkdayClassification::SpecialNonWorking;
        }

        return PayrollWorkdayClassification::Ordinary;
    }

    private function hasHoliday(string $date, Employee $employee): bool
    {
        return Holiday::forDate($date) !== null || $this->holidays->forDate($date, $employee) !== null;
    }
}
