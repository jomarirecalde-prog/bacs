<?php

namespace App\Services\Payroll;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use App\Models\PayrollPremiumRule;
use App\Services\HolidayResolver;
use App\Support\Money;
use App\Support\PayrollSettings;
use Carbon\CarbonPeriod;

class PayrollPremiumCalculator
{
    public function __construct(private readonly HolidayResolver $holidays) {}

    /**
     * @return array{holiday_pay: float, premium_pay: float, breakdown: list<array<string, mixed>>}
     */
    public function calculate(Employee $employee, PayrollPeriod $period, float $hourlyRate, float $dailyRate = 0): array
    {
        if ($hourlyRate <= 0 && $dailyRate <= 0) {
            return ['holiday_pay' => 0.0, 'premium_pay' => 0.0, 'breakdown' => []];
        }

        $start = $period->start_date->toDateString();
        $end = $period->end_date->toDateString();
        $premiumOnly = PayrollSettings::holidayPayMode() !== 'full_multiplier';

        $rules = PayrollPremiumRule::query()->active()->get()->keyBy('scenario_code');
        $holidayPay = 0.0;
        $premiumPay = 0.0;
        $breakdown = [];

        $records = Attendance::query()
            ->where('employee_id', $employee->id)
            ->betweenDates($start, $end)
            ->get();

        foreach ($records as $record) {
            $hours = max(0, ($record->total_minutes ?? 0) / 60);
            if ($hours <= 0 && ($record->overtime_minutes ?? 0) > 0) {
                $hours = ($record->overtime_minutes ?? 0) / 60;
            }
            if ($hours <= 0) {
                continue;
            }

            $date = $record->attendance_date->toDateString();
            $legacyHoliday = Holiday::forDate($date);
            $resolvedHoliday = $this->holidays->forDate($date, $employee);

            if ($legacyHoliday || $resolvedHoliday) {
                $type = $legacyHoliday?->type ?? 'regular';
                $code = $type === 'special' ? 'special_holiday_work' : 'regular_holiday_work';
                /** @var PayrollPremiumRule|null $rule */
                $rule = $rules->get($code);
                if ($rule) {
                    $amount = $this->amountForRule($hours, $hourlyRate, (float) $rule->multiplier, $premiumOnly);
                    if ($rule->pay_component === 'premium_pay') {
                        $premiumPay += $amount;
                    } else {
                        $holidayPay += $amount;
                    }
                    $breakdown[] = [
                        'date' => $date,
                        'label' => $rule->name,
                        'hours' => $hours,
                        'multiplier' => (float) $rule->multiplier,
                        'amount' => $amount,
                    ];
                }

                $otHours = max(0, ($record->overtime_minutes ?? 0) / 60);
                $otRule = $rules->get('holiday_overtime');
                if ($otHours > 0 && $otRule) {
                    $otAmount = $this->amountForRule($otHours, $hourlyRate, (float) $otRule->multiplier, $premiumOnly);
                    $premiumPay += $otAmount;
                    $breakdown[] = [
                        'date' => $date,
                        'label' => $otRule->name,
                        'hours' => $otHours,
                        'multiplier' => (float) $otRule->multiplier,
                        'amount' => $otAmount,
                    ];
                }

                continue;
            }

            if ($record->status === AttendanceStatus::RestDay) {
                $rule = $rules->get('rest_day_work');
                if ($rule) {
                    $amount = $this->amountForRule($hours, $hourlyRate, (float) $rule->multiplier, $premiumOnly);
                    $premiumPay += $amount;
                    $breakdown[] = [
                        'date' => $date,
                        'label' => $rule->name,
                        'hours' => $hours,
                        'multiplier' => (float) $rule->multiplier,
                        'amount' => $amount,
                    ];
                }
            }
        }

        if (PayrollSettings::payUnworkedRegularHolidays() && $dailyRate > 0) {
            $workedDates = $records
                ->filter(fn ($row) => ($row->total_minutes ?? 0) > 0)
                ->map(fn ($row) => $row->attendance_date->toDateString())
                ->flip();

            $unworkedRule = $rules->get('regular_holiday_unworked');
            if ($unworkedRule?->is_active) {
                foreach (CarbonPeriod::create($start, $end) as $day) {
                    $date = $day->toDateString();
                    if (isset($workedDates[$date])) {
                        continue;
                    }

                    $legacyHoliday = Holiday::forDate($date);
                    $resolvedHoliday = $this->holidays->forDate($date, $employee);
                    if (! $legacyHoliday && ! $resolvedHoliday) {
                        continue;
                    }

                    $type = $legacyHoliday?->type ?? 'regular';
                    if ($type !== 'regular') {
                        continue;
                    }

                    $amount = Money::toFloat(Money::round($dailyRate * (float) $unworkedRule->multiplier));
                    $holidayPay += $amount;
                    $breakdown[] = [
                        'date' => $date,
                        'label' => $unworkedRule->name,
                        'hours' => 0,
                        'multiplier' => (float) $unworkedRule->multiplier,
                        'amount' => $amount,
                    ];
                }
            }
        }

        return [
            'holiday_pay' => Money::toFloat(Money::round($holidayPay)),
            'premium_pay' => Money::toFloat(Money::round($premiumPay)),
            'breakdown' => $breakdown,
        ];
    }

    private function amountForRule(float $hours, float $hourlyRate, float $multiplier, bool $premiumOnly): float
    {
        if ($premiumOnly) {
            $extra = max(0, $multiplier - 1);

            return Money::toFloat(Money::round($hours * $hourlyRate * $extra));
        }

        return Money::toFloat(Money::round($hours * $hourlyRate * $multiplier));
    }
}
