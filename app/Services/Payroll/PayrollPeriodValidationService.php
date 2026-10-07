<?php

namespace App\Services\Payroll;

use App\Enums\OvertimeRequestStatus;
use App\Enums\PayrollComputationStatus;
use App\Enums\PayrollPeriodStatus;
use App\Models\OvertimeRequest;
use App\Models\PayrollEmployee;
use App\Models\PayrollPeriod;
use App\Support\PayrollSettings;

class PayrollPeriodValidationService
{
    /**
     * @return array{
     *     can_finalize: bool,
     *     blocking: list<array{code: string, message: string, count?: int}>,
     *     warnings: list<array{code: string, message: string, count?: int}>
     * }
     */
    public function forFinalize(PayrollPeriod $period): array
    {
        $blocking = [];
        $warnings = [];

        if ($period->status !== PayrollPeriodStatus::Approved) {
            $blocking[] = [
                'code' => 'status_not_approved',
                'message' => 'Period must be Approved before it can be finalized.',
            ];
        }

        $payrollCount = $period->payrollEmployees()->count();
        if ($payrollCount === 0) {
            $blocking[] = [
                'code' => 'no_payroll_rows',
                'message' => 'Run Compute full payroll before finalizing.',
            ];
        }

        $errorCount = PayrollEmployee::query()
            ->where('payroll_period_id', $period->id)
            ->where('computation_status', PayrollComputationStatus::Error)
            ->count();

        if ($errorCount > 0) {
            $blocking[] = [
                'code' => 'computation_errors',
                'message' => "{$errorCount} employee(s) have payroll computation errors (e.g. missing salary).",
                'count' => $errorCount,
            ];
        }

        if (PayrollSettings::manualTotalBasicPay()) {
            $missingManualTotal = PayrollEmployee::query()
                ->where('payroll_period_id', $period->id)
                ->where(function ($q) {
                    $q->where('total_basic_pay_manually_set', false)
                        ->orWhere('total_basic_pay', '<=', 0);
                })
                ->count();

            if ($missingManualTotal > 0) {
                $blocking[] = [
                    'code' => 'missing_manual_total_basic_pay',
                    'message' => "{$missingManualTotal} employee(s) still need total basic pay entered manually.",
                    'count' => $missingManualTotal,
                ];
            }
        }

        if (PayrollSettings::overtimeRequiresApproval()) {
            $pendingOt = OvertimeRequest::query()
                ->whereDate('attendance_date', '>=', $period->start_date)
                ->whereDate('attendance_date', '<=', $period->end_date)
                ->whereIn('status', OvertimeRequestStatus::openValues())
                ->count();

            if ($pendingOt > 0) {
                $blocking[] = [
                    'code' => 'pending_overtime',
                    'message' => "{$pendingOt} overtime request(s) still pending approval.",
                    'count' => $pendingOt,
                ];
            }
        }

        $warningRows = PayrollEmployee::query()
            ->where('payroll_period_id', $period->id)
            ->where('computation_status', PayrollComputationStatus::Warning)
            ->count();

        if ($warningRows > 0) {
            $warnings[] = [
                'code' => 'computation_warnings',
                'message' => "{$warningRows} employee(s) have payroll warnings on their payslip computation.",
                'count' => $warningRows,
            ];
        }

        $summaryWarnings = $period->attendanceSummaries()
            ->whereNotNull('warnings')
            ->count();

        if ($summaryWarnings > 0) {
            $warnings[] = [
                'code' => 'attendance_warnings',
                'message' => "{$summaryWarnings} attendance summary row(s) have DTR warnings.",
                'count' => $summaryWarnings,
            ];
        }

        if (! PayrollSettings::overtimeRequiresApproval()) {
            $unreviewedOt = OvertimeRequest::query()
                ->whereDate('attendance_date', '>=', $period->start_date)
                ->whereDate('attendance_date', '<=', $period->end_date)
                ->whereIn('status', OvertimeRequestStatus::openValues())
                ->where('recorded_minutes', '>', 0)
                ->count();

            if ($unreviewedOt > 0) {
                $warnings[] = [
                    'code' => 'overtime_not_reviewed',
                    'message' => "{$unreviewedOt} overtime record(s) were not reviewed (OT approval is optional).",
                    'count' => $unreviewedOt,
                ];
            }
        }

        return [
            'can_finalize' => $blocking === [],
            'blocking' => $blocking,
            'warnings' => $warnings,
        ];
    }
}
