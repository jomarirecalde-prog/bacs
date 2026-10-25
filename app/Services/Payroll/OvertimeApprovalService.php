<?php

namespace App\Services\Payroll;

use App\Enums\EndorsementMode;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Services\EndorsementWorkflowCoordinator;
use App\Enums\OvertimeRequestStatus;
use App\Models\OvertimeRequest;
use App\Models\User;
use App\Enums\ApprovalTransactionType;
use App\Support\DepartmentApprovalScope;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OvertimeApprovalService
{
    public function pendingFor(User $user, ?string $scope = null)
    {
        $query = OvertimeRequest::query()
            ->whereIn('status', OvertimeRequestStatus::openValues())
            ->whereHas('approvalAssignments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'pending'))
            ->whereHas('approvalAssignments', function ($assignment) use ($user) {
                $assignment->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('overtime_approval_assignments.stage', 'overtime_requests.current_approval_stage');
            })
            ->with(['employee.department', 'approvalAssignments']);

        if ($scope === 'endorsement') {
            $query->where('status', OvertimeRequestStatus::PendingEndorsement);
        } elseif ($scope === 'final') {
            $query->where('status', OvertimeRequestStatus::PendingFinalApproval);
        }

        return $query->orderByDesc('attendance_date')
            ->tap(fn ($builder) => DepartmentApprovalScope::constrainToEmployeeDepartments(
                $builder,
                $user,
                ApprovalTransactionType::OvertimeRequest,
            ));
    }

    public function userCanAct(User $user, OvertimeRequest $request): bool
    {
        if ($user->employee?->id === $request->employee_id) {
            return false;
        }

        if (! $request->status?->isOpen() || ! $request->current_approval_stage) {
            return false;
        }

        $request->loadMissing('employee');
        if (! DepartmentApprovalScope::approverCanAccessRequestDepartment($user, $request->employee?->department_id)) {
            return false;
        }

        return $request->approvalAssignments()
            ->where('stage', $request->current_approval_stage)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function decide(OvertimeRequest $request, User $actor, LeaveDecision $decision, string $reason = '', ?int $approvedMinutes = null): OvertimeRequest
    {
        if (! $this->userCanAct($actor, $request)) {
            throw ValidationException::withMessages(['decision' => 'You are not authorized to act on this overtime request.']);
        }

        if ($decision === LeaveDecision::Denied && trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required when rejecting overtime.']);
        }

        return DB::transaction(function () use ($request, $actor, $decision, $reason, $approvedMinutes) {
            $stage = $this->resolveCurrentStage($request);
            if (! $stage) {
                throw ValidationException::withMessages(['decision' => 'This request has no active approval stage.']);
            }

            $assignment = $request->approvalAssignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment || ! $assignment->isPending()) {
                throw ValidationException::withMessages(['decision' => 'You are not an authorized pending approver for the current stage.']);
            }

            $assignment->update([
                'status' => $decision->value,
                'reason' => $reason !== '' ? $reason : null,
                'acted_at' => ManilaTime::now(),
            ]);

            $request->refresh()->load('approvalAssignments');

            if ($decision === LeaveDecision::Denied) {
                return $this->deny($request, $actor, $reason);
            }

            $outcome = $this->evaluateStage($request, $stage);

            if ($outcome === 'pending') {
                return $request->fresh(['employee', 'approvalAssignments']);
            }

            $next = $this->nextStage($request, $stage);

            if ($next) {
                $request->update([
                    'status' => $next === LeaveApprovalStage::CeoFinalApproval
                        ? OvertimeRequestStatus::PendingFinalApproval
                        : OvertimeRequestStatus::PendingEndorsement,
                    'current_approval_stage' => $next->value,
                ]);

                return $request->fresh(['employee', 'approvalAssignments']);
            }

            return $this->approve($request, $actor, $approvedMinutes, $reason);
        });
    }

    public function approve(OvertimeRequest $request, User $actor, ?int $approvedMinutes = null, ?string $notes = null): OvertimeRequest
    {
        $minutes = $approvedMinutes ?? $request->recorded_minutes;

        $request->update([
            'status' => OvertimeRequestStatus::Approved,
            'approved_minutes' => min(max(0, $minutes), $request->recorded_minutes),
            'reviewed_by' => $actor->id,
            'reviewed_at' => ManilaTime::now(),
            'review_notes' => $notes,
        ]);

        return $request->fresh(['employee', 'approvalAssignments']);
    }

    public function deny(OvertimeRequest $request, User $actor, string $reason): OvertimeRequest
    {
        $request->update([
            'status' => OvertimeRequestStatus::Denied,
            'approved_minutes' => 0,
            'reviewed_by' => $actor->id,
            'reviewed_at' => ManilaTime::now(),
            'review_notes' => $reason,
        ]);

        return $request->fresh(['employee', 'approvalAssignments']);
    }

    /** @return 'pending'|'approved'|'denied' */
    private function evaluateStage(OvertimeRequest $request, LeaveApprovalStage $stage): string
    {
        $rows = $request->approvalAssignments->where('stage', $stage->value)->values();
        $pending = $rows->filter->isPending();
        $approved = $rows->filter->isApproved();
        $denied = $rows->filter->isDenied();
        $total = $rows->count();

        if ($total === 0) {
            return 'approved';
        }

        if ($denied->isNotEmpty()) {
            return 'denied';
        }

        if ($stage->usesCentralEndorsementRules()) {
            $mode = EndorsementMode::tryFrom((string) $request->endorsement_mode) ?? EndorsementMode::Parallel;
            $rule = LeaveParallelRule::tryFrom((string) $request->endorsement_parallel_rule) ?? LeaveParallelRule::All;

            return app(EndorsementWorkflowCoordinator::class)->evaluateEndorsementStage($rows, $mode, $rule);
        }

        return $approved->isNotEmpty() ? 'approved' : 'pending';
    }

    private function nextStage(OvertimeRequest $request, LeaveApprovalStage $from): ?LeaveApprovalStage
    {
        $stages = [
            LeaveApprovalStage::ImmediateSupervisor,
            LeaveApprovalStage::DepartmentHead,
            LeaveApprovalStage::CeoFinalApproval,
        ];

        $passed = false;
        foreach ($stages as $stage) {
            if ($passed) {
                $hasAssignments = $request->approvalAssignments
                    ->where('stage', $stage->value)
                    ->isNotEmpty();

                if ($hasAssignments) {
                    return $stage;
                }
            }

            if ($stage === $from) {
                $passed = true;
            }
        }

        return null;
    }

    private function resolveCurrentStage(OvertimeRequest $request): ?LeaveApprovalStage
    {
        $current = $request->current_approval_stage;

        if ($current instanceof LeaveApprovalStage) {
            return $current;
        }

        return LeaveApprovalStage::tryFrom((string) $current);
    }
}
