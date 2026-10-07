<?php

namespace App\Services\Payroll;

use App\Enums\PayrollPeriodStatus;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\PayrollSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollPeriodService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PayrollAttendanceAggregator $attendance,
        private readonly PayrollEngine $payroll,
        private readonly OvertimeRequestSyncService $overtimeSync,
        private readonly PayrollPeriodValidationService $validation,
        private readonly PayrollPayslipNotificationService $payslipNotifications,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $actor): PayrollPeriod
    {
        if ($data['start_date'] > $data['end_date']) {
            throw ValidationException::withMessages([
                'end_date' => 'End date must be on or after the start date.',
            ]);
        }

        $exists = PayrollPeriod::query()
            ->where('start_date', $data['start_date'])
            ->where('end_date', $data['end_date'])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'start_date' => 'A payroll period already exists for this date range.',
            ]);
        }

        $period = PayrollPeriod::query()->create([
            'period_name' => $data['period_name'],
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'payroll_date' => $data['payroll_date'] ?? null,
            'status' => PayrollPeriodStatus::Draft,
            'period_key' => $data['period_key'] ?? null,
            'created_by' => $actor->id,
        ]);

        $this->audit->log($actor, 'payroll_period_created', 'Payroll', $period->id, "Payroll period {$period->period_name} created.");

        return $period;
    }

    public function computeAttendance(PayrollPeriod $period, User $actor): array
    {
        if (! $period->status?->allowsRecomputation()) {
            throw ValidationException::withMessages([
                'period' => 'This payroll period is locked and cannot be recomputed.',
            ]);
        }

        return DB::transaction(function () use ($period, $actor) {
            $this->overtimeSync->syncPeriod($period);
            $result = $this->attendance->aggregatePeriod($period);

            $period->update([
                'status' => PayrollPeriodStatus::Computed,
            ]);

            $this->audit->log(
                $actor,
                'payroll_attendance_computed',
                'Payroll',
                $period->id,
                "Attendance summary computed for {$result['employees']} employees ({$result['warnings']} warnings)."
            );

            return $result;
        });
    }

    public function computePayroll(PayrollPeriod $period, User $actor): array
    {
        if (! $period->status?->allowsRecomputation()) {
            throw ValidationException::withMessages([
                'period' => 'This payroll period is locked and cannot be recomputed.',
            ]);
        }

        return DB::transaction(function () use ($period, $actor) {
            $this->overtimeSync->syncPeriod($period);
            if ($period->attendanceSummaries()->count() === 0) {
                $this->attendance->aggregatePeriod($period);
            }

            $result = $this->payroll->computePeriod($period);

            $period->update(['status' => PayrollPeriodStatus::Computed]);

            $this->audit->log(
                $actor,
                'payroll_computed',
                'Payroll',
                $period->id,
                "Payroll computed for {$result['employees']} employees ({$result['warnings']} warnings, {$result['errors']} errors)."
            );

            return $result;
        });
    }

    /**
     * @param  array{acknowledge_warnings?: bool}  $context
     */
    public function transition(PayrollPeriod $period, PayrollPeriodStatus $to, User $actor, array $context = []): PayrollPeriod
    {
        if ($period->status === PayrollPeriodStatus::Paid) {
            throw ValidationException::withMessages(['status' => 'Paid payroll periods cannot be changed.']);
        }

        $from = $period->status;
        $allowed = match ($from) {
            PayrollPeriodStatus::Draft => [PayrollPeriodStatus::Computed, PayrollPeriodStatus::Cancelled],
            PayrollPeriodStatus::Computed => [PayrollPeriodStatus::ForReview, PayrollPeriodStatus::Draft, PayrollPeriodStatus::Cancelled],
            PayrollPeriodStatus::ForReview => [PayrollPeriodStatus::Approved, PayrollPeriodStatus::Computed, PayrollPeriodStatus::Cancelled],
            PayrollPeriodStatus::Approved => [PayrollPeriodStatus::Finalized, PayrollPeriodStatus::ForReview, PayrollPeriodStatus::Cancelled],
            PayrollPeriodStatus::Finalized => [PayrollPeriodStatus::Paid, PayrollPeriodStatus::Approved],
            PayrollPeriodStatus::Paid => [],
            default => [],
        };

        if (! in_array($to, $allowed, true) && $from !== $to) {
            throw ValidationException::withMessages([
                'status' => "Cannot change status from {$from->label()} to {$to->label()}.",
            ]);
        }

        $validationReport = null;
        if ($to === PayrollPeriodStatus::Finalized) {
            $validationReport = $this->validation->forFinalize($period);

            if (! $validationReport['can_finalize']) {
                throw ValidationException::withMessages([
                    'status' => collect($validationReport['blocking'])->pluck('message')->implode(' '),
                ]);
            }

            if (
                PayrollSettings::blockFinalizeOnWarnings()
                && $validationReport['warnings'] !== []
                && ! ($context['acknowledge_warnings'] ?? false)
            ) {
                throw ValidationException::withMessages([
                    'status' => 'Resolve warnings or confirm finalize with acknowledge_warnings.',
                    'warnings' => collect($validationReport['warnings'])->pluck('message')->all(),
                ]);
            }
        }

        $updates = ['status' => $to];

        if ($to === PayrollPeriodStatus::Approved) {
            $updates['approved_by'] = $actor->id;
            $updates['approved_at'] = now();
        }

        if ($to === PayrollPeriodStatus::Finalized) {
            $updates['finalized_by'] = $actor->id;
            $updates['finalized_at'] = now();
        }

        $period->update($updates);

        $this->audit->log(
            $actor,
            'payroll_period_status_changed',
            'Payroll',
            $period->id,
            "Payroll period status: {$from->label()} → {$to->label()}.",
            metadata: $validationReport ? ['finalize_validation' => $validationReport] : null
        );

        if ($to === PayrollPeriodStatus::Paid && PayrollSettings::emailPayslipsOnPaid()) {
            $sent = $this->payslipNotifications->notifyPeriod($period->fresh(), $actor);
            $this->audit->log(
                $actor,
                'payroll_payslips_emailed',
                'Payroll',
                $period->id,
                "Payslip notification sent to {$sent} employee(s).",
                metadata: ['sent' => $sent]
            );
        }

        return $period->fresh();
    }

    public function notifyPayslips(PayrollPeriod $period, User $actor): int
    {
        if (! in_array($period->status, [PayrollPeriodStatus::Finalized, PayrollPeriodStatus::Paid], true)) {
            throw ValidationException::withMessages([
                'period' => 'Payslip notifications can only be sent for finalized or paid periods.',
            ]);
        }

        $sent = $this->payslipNotifications->notifyPeriod($period, $actor);

        $this->audit->log(
            $actor,
            'payroll_payslips_emailed',
            'Payroll',
            $period->id,
            "Payslip notification sent to {$sent} employee(s).",
            metadata: ['sent' => $sent, 'manual' => true]
        );

        return $sent;
    }
}
