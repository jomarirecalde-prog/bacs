<?php

namespace App\Services\Payroll;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\OvertimeRequestStatus;
use App\Models\OvertimeRequest;
use App\Models\User;
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

        return $query->orderByDesc('attendance_date');
    }

    public function userCanAct(User $user, OvertimeRequest $request): bool
    {
        if ($user->employee?->id === $request->employee_id) {
            return false;
        }

        if (! $request->status?->isOpen() || ! $request->current_approval_stage) {
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
            $stage = LeaveApprovalStage::tryFrom((string) $request->current_approval_stage);
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

        if ($stage->isParallel() || $total > 1) {
            return $approved->count() === $total ? 'approved' : 'pending';
        }

        return $approved->isNotEmpty() ? 'approved' : 'pending';
    }

    private function nextStage(OvertimeRequest $request, LeaveApprovalStage $from): ?LeaveApprovalStage
    {
        if ($from === LeaveApprovalStage::ImmediateSupervisor) {
            $hasFinal = $request->approvalAssignments
                ->where('stage', LeaveApprovalStage::CeoFinalApproval->value)
                ->isNotEmpty();

            return $hasFinal ? LeaveApprovalStage::CeoFinalApproval : null;
        }

        return null;
    }
}
