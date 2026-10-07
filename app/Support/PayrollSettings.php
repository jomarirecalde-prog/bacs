<?php

namespace App\Support;

use App\Models\Setting;

final class PayrollSettings
{
    public static function overtimeMultiplier(): float
    {
        return (float) Setting::get('payroll_overtime_multiplier', config('payroll.overtime_multiplier', 1.25));
    }

    public static function holidayPayMode(): string
    {
        return (string) Setting::get('payroll_holiday_pay_mode', config('payroll.holiday_pay_mode', 'premium_only'));
    }

    public static function overtimeRequiresApproval(): bool
    {
        $value = Setting::get('payroll_ot_requires_approval', config('payroll.overtime_requires_approval', false));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function philhealthRate(): float
    {
        return (float) Setting::get('payroll_philhealth_rate', config('payroll.statutory.philhealth_rate', 0));
    }

    public static function hdmfAmount(): float
    {
        return (float) Setting::get('payroll_hdmf_amount', config('payroll.statutory.hdmf_employee_amount', 0));
    }

    public static function workingDaysBasis(): int
    {
        return (int) Setting::get('payroll_working_days_basis', config('payroll.working_days_basis', 22));
    }

    public static function sssFromBracketTable(): bool
    {
        $value = Setting::get('payroll_sss_from_brackets', config('payroll.statutory.sss_from_brackets', false));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function blockFinalizeOnWarnings(): bool
    {
        $value = Setting::get('payroll_block_finalize_on_warnings', config('payroll.block_finalize_on_warnings', true));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function emailPayslipsOnPaid(): bool
    {
        $value = Setting::get('payroll_email_on_paid', config('payroll.email_payslips_on_paid', false));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function overtimeUseCentralApproval(): bool
    {
        $value = Setting::get('payroll_ot_central_approval', config('payroll.overtime_central_approval', false));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function taxFromBracketTable(): bool
    {
        $value = Setting::get('payroll_tax_from_brackets', config('payroll.statutory.tax_from_brackets', false));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function payUnworkedRegularHolidays(): bool
    {
        $value = Setting::get('payroll_unworked_regular_holiday_pay', config('payroll.pay_unworked_regular_holidays', false));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public static function manualTotalBasicPay(): bool
    {
        $value = Setting::get('payroll_manual_total_basic_pay', config('payroll.manual_total_basic_pay', true));

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
