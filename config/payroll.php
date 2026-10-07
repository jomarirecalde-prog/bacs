<?php

return [

    /*
    | When true, total basic pay is not auto-applied on compute; payroll admin enters it
    | per employee (DTR-derived basic pay and ALU remain informational).
    */
    'manual_total_basic_pay' => filter_var(env('PAYROLL_MANUAL_TOTAL_BASIC_PAY', true), FILTER_VALIDATE_BOOL),

    'working_days_basis' => (int) env('PAYROLL_WORKING_DAYS_BASIS', 22),

    'travel_order_counts_as_paid_day' => filter_var(env('PAYROLL_TO_PAID_DAY', true), FILTER_VALIDATE_BOOL),

    /*
    | When true, only approved OT records (future module) count toward payroll OT pay.
    | Until OT approval exists, recorded_ot_hours from DTR is stored for review.
    */
    'overtime_requires_approval' => filter_var(env('PAYROLL_OT_REQUIRES_APPROVAL', false), FILTER_VALIDATE_BOOL),

    'attendance' => [
        'half_day_absent_fraction' => 0.5,
    ],

    'overtime_multiplier' => (float) env('PAYROLL_OT_MULTIPLIER', 1.25),

    'formulas' => [
        'minute_rate_from' => env('PAYROLL_MINUTE_RATE_FROM', 'hourly'), // hourly|daily
    ],

    /*
    | premium_only: pay (multiplier - 1) × hourly × hours (basic day already in basic pay)
    | full_multiplier: pay multiplier × hourly × hours
    */
    'holiday_pay_mode' => env('PAYROLL_HOLIDAY_PAY_MODE', 'premium_only'),

    'statutory' => [
        'philhealth_rate' => (float) env('PAYROLL_PHILHEALTH_RATE', 0),
        'hdmf_employee_amount' => (float) env('PAYROLL_HDMF_AMOUNT', 0),
        'sss_from_brackets' => filter_var(env('PAYROLL_SSS_FROM_BRACKETS', false), FILTER_VALIDATE_BOOL),
        'tax_from_brackets' => filter_var(env('PAYROLL_TAX_FROM_BRACKETS', false), FILTER_VALIDATE_BOOL),
    ],

    'block_finalize_on_warnings' => filter_var(env('PAYROLL_BLOCK_FINALIZE_ON_WARNINGS', true), FILTER_VALIDATE_BOOL),

    'email_payslips_on_paid' => filter_var(env('PAYROLL_EMAIL_ON_PAID', false), FILTER_VALIDATE_BOOL),

    'overtime_central_approval' => filter_var(env('PAYROLL_OT_CENTRAL_APPROVAL', false), FILTER_VALIDATE_BOOL),

    'pay_unworked_regular_holidays' => filter_var(env('PAYROLL_UNWORKED_HOLIDAY_PAY', false), FILTER_VALIDATE_BOOL),

];
