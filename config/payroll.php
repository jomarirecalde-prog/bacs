<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Supervisor payroll access
    |--------------------------------------------------------------------------
    |
    | When true (default), supervisors may view/export the full organization
    | payroll register. Set PAYROLL_SUPERVISOR_FULL_ACCESS=false to restrict
    | payroll views to administrators only.
    |
    */

    'supervisor_full_access' => filter_var(
        env('PAYROLL_SUPERVISOR_FULL_ACCESS', true),
        FILTER_VALIDATE_BOOL
    ),

    'overtime_multiplier' => 1.25,
    'holiday_pay_mode' => 'premium_only',
    'overtime_requires_approval' => false,
    'working_days_basis' => 22,
    'block_finalize_on_warnings' => true,
    'email_payslips_on_paid' => false,
    'overtime_central_approval' => false,
    'pay_unworked_regular_holidays' => false,
    'manual_total_basic_pay' => true,

    'statutory' => [
        'philhealth_rate' => 0,
        'hdmf_employee_amount' => 0,
        'sss_from_brackets' => false,
        'tax_from_brackets' => false,
    ],

];
