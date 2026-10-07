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

];
