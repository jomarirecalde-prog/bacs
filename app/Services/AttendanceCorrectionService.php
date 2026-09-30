<?php

namespace App\Services;

use App\Enums\ApprovalTransactionType;
use App\Enums\AttendanceCorrectionStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\AttendancePunchType;
use App\Models\Attendance;
use App\Models\AttendanceCorrectionRequest;
use App\Models\Employee;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceCorrectionService
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly NotificationService $notifications,
        private readonly EmailNotificationService $emailNotifications,
        private readonly AuditLogger $auditLogger,
        private readonly CentralApprovalWorkflowService $centralApproval,
    ) {}

    public function submit(Employee $employee, array $data): AttendanceCorrectionRequest
    {
        $date = $data['attendance_date'];
        $type = AttendancePunchType::from($data['punch_type']);
        $requested = ManilaTime::combineDateAndTime($date, $data['requested_time']);

        if ($this->hasPendingFor($employee->id, $date, $type)) {
            throw ValidationException::withMessages([
                'punch_type' => 'A pending correction already exists for '.$type->label().' on this date.',
            ]);
        }

        $record = Attendance::query()
            ->where('employee_id', $employee->id)
            ->onDate($date)
            ->first();

        $column = $type->column();
        $original = $record?->{$column};

        if ($original && $original->format('H:i') === $requested->format('H:i')) {
            throw ValidationException::withMessages([
                'requested_time' => 'The requested time matches the current recorded value.',
            ]);
        }

        return DB::transaction(function () use ($employee, $date, $type, $requested, $original, $record, $data) {
            $this->centralApproval->assertCanSubmit(ApprovalTransactionType::Pardon);
            $config = $this->centralApproval->configurationFor(ApprovalTransactionType::Pardon);
            $meta = $this->centralApproval->initialStageMeta($config);
            $status = $this->centralApproval->pardonStatusAfterBootstrap($config);

            $request = AttendanceCorrectionRequest::query()->create([
                'employee_id' => $employee->id,
                'attendance_id' => $record?->id,
                'attendance_date' => $date,
                'punch_type' => $type->value,
                'original_value' => $original,
                'requested_value' => $requested,
                'reason' => $data['reason'],
                'status' => $status->value,
                'current_approval_stage' => $meta['stage']?->value,
            ]);

            $this->centralApproval->bootstrapPardon($request, $employee);

            if ($meta['stage'] === null) {
                return $this->approve($employee->user ?? User::query()->where('role', 'admin')->first(), $request, 'Auto-approved (no endorsement/final approval configured).');
            }

            $this->auditLogger->log(
                $employee->user,
                'attendance_correction_submitted',
                'AttendanceCorrectionRequest',
                $request->id,
                "{$employee->fullName()} requested a correction for {$type->label()} on {$date}."
            );

            foreach ($request->approvalAssignments()->where('status', 'pending')->with('user')->get() as $assignment) {
                if ($assignment->user) {
                    $this->notifications->notify(
                        $assignment->user,
                        'Pardon / time correction for endorsement',
                        "{$employee->fullName()} requested a correction for {$type->label()} on {$date}.",
                        'warning',
                        route('pardon.approvals.show', $request)
                    );
                }
            }

            if ($employee->user) {
                $this->notifications->notify(
                    $employee->user,
                    'Correction request submitted',
                    'Your request for '.$type->label().' on '.$date.' is pending admin review.',
                    'info',
                    route('employee.attendance-corrections.show', $request)
                );
            }

            return $request;
        });
    }

    public function approve(User $reviewer, AttendanceCorrectionRequest $request, ?string $notes = null): AttendanceCorrectionRequest
    {
        if (! $request->status?->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'This correction request has already been processed.',
            ]);
        }

        return DB::transaction(function () use ($reviewer, $request, $notes) {
            $employee = $request->employee;
            $date = $request->attendance_date->toDateString();
            $type = AttendancePunchType::from($request->punch_type);
            $field = $type->column();

            $record = Attendance::query()
                ->where('employee_id', $employee->id)
                ->onDate($date)
                ->lockForUpdate()
                ->first();

            if (! $record) {
                $record = Attendance::query()->create([
                    'employee_id' => $employee->id,
                    'attendance_date' => $date,
                    'status' => 'incomplete',
                    'is_manual' => false,
                ]);
            }

            $updateData = [
                $field => $request->requested_value->format('H:i'),
                'reason' => 'Employee correction request #'.$request->id.': '.$request->reason.($notes ? ' | Admin notes: '.$notes : ''),
            ];

            $this->attendance->updateRecord($reviewer, $record, $updateData);

            $request->update([
                'attendance_id' => $record->fresh()->id,
                'status' => AttendanceCorrectionStatus::Approved->value,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => ManilaTime::now(),
                'review_notes' => $notes,
            ]);

            if ($employee->user) {
                $this->notifications->notify(
                    $employee->user,
                    'DTR correction approved',
                    'Your '.$type->label().' correction for '.$date.' was approved.',
                    'success',
                    route('employee.dtr')
                );
            }

            $this->emailNotifications->correctionApproved($request->fresh(['employee.user']), $reviewer);

            return $request->fresh(['employee', 'reviewer']);
        });
    }

    public function reject(User $reviewer, AttendanceCorrectionRequest $request, ?string $notes = null): AttendanceCorrectionRequest
    {
        if (! $request->status?->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'This correction request has already been processed.',
            ]);
        }

        $request->update([
            'status' => AttendanceCorrectionStatus::Rejected->value,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => ManilaTime::now(),
            'review_notes' => $notes,
        ]);

        $type = $request->punchType();

        if ($request->employee?->user) {
            $this->notifications->notify(
                $request->employee->user,
                'DTR correction rejected',
                'Your '.$type?->label().' correction for '.$request->attendance_date->toDateString().' was not approved.',
                'warning',
                route('employee.attendance-corrections.show', $request)
            );
        }

        $this->emailNotifications->correctionRejected($request->fresh(['employee.user']), $reviewer, $notes);

        return $request->fresh(['employee', 'reviewer']);
    }

    public function cancel(Employee $employee, AttendanceCorrectionRequest $request): AttendanceCorrectionRequest
    {
        if ($request->employee_id !== $employee->id || ! $request->status?->isOpen()) {
            throw ValidationException::withMessages([
                'request' => 'This correction request cannot be cancelled.',
            ]);
        }

        $request->update(['status' => AttendanceCorrectionStatus::Cancelled->value]);

        return $request->fresh();
    }

    public function hasPendingFor(int $employeeId, string $date, AttendancePunchType $type): bool
    {
        return AttendanceCorrectionRequest::query()
            ->where('employee_id', $employeeId)
            ->forDate($date)
            ->where('punch_type', $type->value)
            ->pending()
            ->exists();
    }

    public function hasPendingForDate(int $employeeId, string $date): bool
    {
        return AttendanceCorrectionRequest::query()
            ->where('employee_id', $employeeId)
            ->forDate($date)
            ->pending()
            ->exists();
    }

    public function pendingMessageFor(Employee $employee, string $date): ?string
    {
        $pending = AttendanceCorrectionRequest::query()
            ->where('employee_id', $employee->id)
            ->forDate($date)
            ->pending()
            ->first();

        if (! $pending) {
            return null;
        }

        return 'You have a pending DTR correction request for '.$pending->punchLabel().' on '.$date.'. Please wait for admin review.';
    }
}
