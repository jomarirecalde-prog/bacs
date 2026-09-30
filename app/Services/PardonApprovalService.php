<?php

namespace App\Services;

use App\Enums\AttendanceCorrectionStatus;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Models\AttendanceCorrectionRequest;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PardonApprovalService
{
    public function __construct(
        private readonly AttendanceCorrectionService $corrections,
    ) {}

    public function pendingFor(User $user, ?string $scope = null)
    {
        $query = AttendanceCorrectionRequest::query()
            ->whereIn('status', [
                AttendanceCorrectionStatus::PendingEndorsement->value,
                AttendanceCorrectionStatus::PendingFinalApproval->value,
            ])
            ->whereHas('approvalAssignments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'pending'))
            ->whereHas('approvalAssignments', function ($assignment) use ($user) {
                $assignment->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('attendance_correction_approval_assignments.stage', 'attendance_correction_requests.current_approval_stage');
            })
            ->with(['employee.department', 'approvalAssignments']);

        if ($scope === 'endorsement') {
            $query->where('status', AttendanceCorrectionStatus::PendingEndorsement);
        } elseif ($scope === 'final') {
            $query->where('status', AttendanceCorrectionStatus::PendingFinalApproval);
        }

        return $query->latest('id');
    }

    public function userCanAct(User $user, AttendanceCorrectionRequest $request): bool
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

    public function decide(AttendanceCorrectionRequest $request, User $actor, LeaveDecision $decision, string $reason = ''): AttendanceCorrectionRequest
    {
        if (! $this->userCanAct($actor, $request)) {
            throw ValidationException::withMessages(['decision' => 'You are not authorized to act on this correction request.']);
        }

        if ($actor->employee?->id === $request->employee_id) {
            throw ValidationException::withMessages(['decision' => 'You cannot endorse your own correction request.']);
        }

        if (! $request->status?->isOpen()) {
            throw ValidationException::withMessages(['decision' => 'This request is no longer awaiting action.']);
        }

        $stage = LeaveApprovalStage::tryFrom((string) $request->current_approval_stage);
        if (! $stage) {
            throw ValidationException::withMessages(['decision' => 'This request has no active approval stage.']);
        }

        if ($decision === LeaveDecision::Denied && trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'A reason is required when rejecting a correction request.']);
        }

        return DB::transaction(function () use ($request, $actor, $decision, $reason, $stage) {
            $assignment = $request->approvalAssignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment || ! $assignment->isPending()) {
                throw ValidationException::withMessages(['decision' => 'You are not an authorized pending endorser for the current stage.']);
            }

            $assignment->update([
                'status' => $decision->value,
                'reason' => $reason !== '' ? $reason : null,
                'acted_at' => ManilaTime::now(),
            ]);

            $request->refresh()->load('approvalAssignments');

            if ($decision === LeaveDecision::Denied) {
                $request->update([
                    'status' => AttendanceCorrectionStatus::Rejected,
                    'reviewed_by' => $actor->id,
                    'reviewed_at' => ManilaTime::now(),
                    'review_notes' => $reason,
                ]);

                return $request->fresh(['employee', 'approvalAssignments']);
            }

            $outcome = $this->evaluateStage($request, $stage);

            if ($outcome === 'pending') {
                return $request->fresh(['employee', 'approvalAssignments']);
            }

            $next = $this->nextStage($request, $stage);

            if ($next) {
                $request->update([
                    'status' => $next === LeaveApprovalStage::CeoFinalApproval
                        ? AttendanceCorrectionStatus::PendingFinalApproval
                        : AttendanceCorrectionStatus::PendingEndorsement,
                    'current_approval_stage' => $next->value,
                ]);

                return $request->fresh(['employee', 'approvalAssignments']);
            }

            return $this->corrections->approve($actor, $request, 'Approved via configured workflow.');
        });
    }

    /** @return 'pending'|'approved'|'denied' */
    private function evaluateStage(AttendanceCorrectionRequest $request, LeaveApprovalStage $stage): string
    {
        $rows = $request->approvalAssignments->where('stage', $stage)->values();
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

    private function nextStage(AttendanceCorrectionRequest $request, LeaveApprovalStage $from): ?LeaveApprovalStage
    {
        if ($from === LeaveApprovalStage::ImmediateSupervisor) {
            $hasFinal = $request->approvalAssignments->where('stage', LeaveApprovalStage::CeoFinalApproval)->isNotEmpty();

            return $hasFinal ? LeaveApprovalStage::CeoFinalApproval : null;
        }

        return null;
    }
}
