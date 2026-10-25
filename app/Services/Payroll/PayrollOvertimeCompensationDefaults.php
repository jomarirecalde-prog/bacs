<?php

namespace App\Services\Payroll;

use App\Enums\PayrollWorkdayClassification;

final class PayrollOvertimeCompensationDefaults
{
    /**
     * Reference OT compensation matrix (configurable; not legal advice).
     *
     * @return list<array{
     *     condition_code: string,
     *     label: string,
     *     workday_classification: PayrollWorkdayClassification,
     *     is_rest_day: bool,
     *     is_night_shift: bool,
     *     is_overtime: bool,
     *     multiplier: float
     * }>
     */
    public static function rows(): array
    {
        $ordinary = PayrollWorkdayClassification::Ordinary;
        $special = PayrollWorkdayClassification::SpecialNonWorking;
        $regular = PayrollWorkdayClassification::RegularHoliday;
        $doubleSpecial = PayrollWorkdayClassification::DoubleSpecialNonWorking;
        $doubleHoliday = PayrollWorkdayClassification::DoubleHoliday;

        return [
            ['condition_code' => 'ordinary_ot', 'label' => 'Ordinary day, OT', 'workday_classification' => $ordinary, 'is_rest_day' => false, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 1.25],
            ['condition_code' => 'rest_day_ot', 'label' => 'Rest day, OT', 'workday_classification' => $ordinary, 'is_rest_day' => true, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 1.69],
            ['condition_code' => 'special_ot', 'label' => 'Special non-working day, OT', 'workday_classification' => $special, 'is_rest_day' => false, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 1.69],
            ['condition_code' => 'special_rest_ot', 'label' => 'Special non-working day, rest day, OT', 'workday_classification' => $special, 'is_rest_day' => true, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 1.95],
            ['condition_code' => 'double_special_rest_ot', 'label' => 'Double special non-working days, rest day, OT', 'workday_classification' => $doubleSpecial, 'is_rest_day' => true, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 2.535],
            ['condition_code' => 'regular_holiday_ot', 'label' => 'Regular holiday, OT', 'workday_classification' => $regular, 'is_rest_day' => false, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 2.60],
            ['condition_code' => 'regular_holiday_rest_ot', 'label' => 'Regular holiday, rest day, OT', 'workday_classification' => $regular, 'is_rest_day' => true, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 3.38],
            ['condition_code' => 'double_holiday_ot', 'label' => 'Double holiday, OT', 'workday_classification' => $doubleHoliday, 'is_rest_day' => false, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 3.90],
            ['condition_code' => 'double_holiday_rest_ot', 'label' => 'Double holiday, rest day, OT', 'workday_classification' => $doubleHoliday, 'is_rest_day' => true, 'is_night_shift' => false, 'is_overtime' => true, 'multiplier' => 5.07],
            ['condition_code' => 'ordinary_night_ot', 'label' => 'Ordinary day, night shift, OT', 'workday_classification' => $ordinary, 'is_rest_day' => false, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 1.375],
            ['condition_code' => 'rest_day_night_ot', 'label' => 'Rest day, night shift, OT', 'workday_classification' => $ordinary, 'is_rest_day' => true, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 1.859],
            ['condition_code' => 'special_night_ot', 'label' => 'Special non-working day, night shift, OT', 'workday_classification' => $special, 'is_rest_day' => false, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 1.859],
            ['condition_code' => 'special_rest_night_ot', 'label' => 'Special non-working day, rest day, night shift, OT', 'workday_classification' => $special, 'is_rest_day' => true, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 2.145],
            ['condition_code' => 'double_special_rest_night_ot', 'label' => 'Double special non-working days, rest day, night shift, OT', 'workday_classification' => $doubleSpecial, 'is_rest_day' => true, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 2.7885],
            ['condition_code' => 'regular_holiday_night_ot', 'label' => 'Regular holiday, night shift, OT', 'workday_classification' => $regular, 'is_rest_day' => false, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 2.86],
            ['condition_code' => 'regular_holiday_rest_night_ot', 'label' => 'Regular holiday, rest day, night shift, OT', 'workday_classification' => $regular, 'is_rest_day' => true, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 3.718],
            ['condition_code' => 'double_holiday_night_ot', 'label' => 'Double holiday, night shift, OT', 'workday_classification' => $doubleHoliday, 'is_rest_day' => false, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 4.29],
            ['condition_code' => 'double_holiday_rest_night_ot', 'label' => 'Double holiday, rest day, night shift, OT', 'workday_classification' => $doubleHoliday, 'is_rest_day' => true, 'is_night_shift' => true, 'is_overtime' => true, 'multiplier' => 5.577],
            // Non-OT night shift reference rates (stored for configuration; not used as OT multipliers).
            ['condition_code' => 'regular_holiday_rest_night', 'label' => 'Regular holiday, rest day, night shift (non-OT reference)', 'workday_classification' => $regular, 'is_rest_day' => true, 'is_night_shift' => true, 'is_overtime' => false, 'multiplier' => 2.86],
            ['condition_code' => 'double_holiday_night', 'label' => 'Double holiday, night shift (non-OT reference)', 'workday_classification' => $doubleHoliday, 'is_rest_day' => false, 'is_night_shift' => true, 'is_overtime' => false, 'multiplier' => 3.30],
            ['condition_code' => 'double_holiday_rest_night', 'label' => 'Double holiday, rest day, night shift (non-OT reference)', 'workday_classification' => $doubleHoliday, 'is_rest_day' => true, 'is_night_shift' => true, 'is_overtime' => false, 'multiplier' => 4.29],
        ];
    }
}
