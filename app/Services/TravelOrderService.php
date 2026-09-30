<?php

namespace App\Services;

use App\Enums\ApprovalTransactionType;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Enums\TravelOrderStatus;
use App\Enums\TravelTransportation;
use App\Enums\UserRole;
use App\Models\Employee;
use App\Models\LeaveApprovalWorkflow;
use App\Models\TravelOrder;
use App\Models\TravelOrderApprovalAction;
use App\Models\TravelOrderApprovalAssignment;
use App\Models\TravelOrderAttachment;
use App\Models\TravelOrderModificationLog;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class TravelOrderService
{
    public function __construct(
        private readonly TravelOrderNotificationService $notifier,
        private readonly EmailNotificationService $emailNotifications,
        private readonly AuditLogger $audit,
        private readonly CeoResolver $ceo,
        private readonly LeaveWorkflowService $workflows,
        private readonly CentralApprovalWorkflowService $centralApproval,
    ) {}

    /** @return Collection<int, array{id:int,name:string,position:?string,department:?string,employee_number:?string}> */
    public function searchEmployees(string $term, int $limit = 20): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return collect();
        }

        return Employee::query()
            ->active()
            ->with('department:id,name')
            ->where(function ($query) use ($term) {
                $like = '%'.$term.'%';
                $query->where('full_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like)
                    ->orWhere('position', 'like', $like)
                    ->orWhereHas('department', fn ($dept) => $dept->where('name', 'like', $like));
            })
            ->orderBy('full_name')
            ->limit($limit)
            ->get()
            ->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'name' => $employee->fullName(),
                'position' => $employee->position,
                'department' => $employee->department?->name,
                'employee_number' => $employee->employee_number,
            ]);
    }

    public function saveDraft(Employee $requester, User $actor, array $data, ?TravelOrder $existing = null): TravelOrder
    {
        $this->assertCanCreate($requester, $actor);
        $payload = $this->validatedPayload($data, false);
        $travelerIds = $this->resolveTravelerIds($requester, $data);

        return DB::transaction(function () use ($requester, $actor, $payload, $travelerIds, $existing, $data) {
            if ($existing) {
                $this->assertRequesterOwnsDraft($existing, $requester);
                $existing->update($payload);
                $order = $existing;
            } else {
                $order = TravelOrder::query()->create(array_merge($payload, [
                    'travel_order_number' => $this->nextNumber(),
                    'requester_id' => $requester->id,
                    'department_id' => $requester->department_id,
                    'status' => TravelOrderStatus::Draft,
                    'date_requested' => ManilaTime::now(),
                    'submitted_by' => $actor->id,
                ]));
            }

            $this->syncPersonnel($order, $travelerIds);
            $this->syncDestinations($order, $data['destinations'] ?? []);
            $this->storeAttachments($order, $actor, $data['attachments'] ?? []);

            return $order->fresh([
                'requester.department',
                'personnel.employee.department',
                'destinations',
                'attachments',
            ]);
        });
    }

    public function submit(Employee $requester, User $actor, array $data, ?TravelOrder $existing = null): TravelOrder
    {
        $this->assertCanCreate($requester, $actor);
        $payload = $this->validatedPayload($data, true);
        $travelerIds = $this->resolveTravelerIds($requester, $data);

        if ($travelerIds === []) {
            throw ValidationException::withMessages([
                'traveler_ids' => 'Select at least one traveler for this travel order.',
            ]);
        }

        return DB::transaction(function () use ($requester, $actor, $payload, $travelerIds, $existing, $data) {
            $this->centralApproval->assertCanSubmit(ApprovalTransactionType::TravelOrder);
            $centralConfig = $this->centralApproval->configurationFor(ApprovalTransactionType::TravelOrder);
            $workflow = LeaveApprovalWorkflow::forDepartment($requester->department_id);

            if ($existing) {
                $this->assertRequesterOwnsDraft($existing, $requester);
                $existing->update(array_merge($payload, [
                    'workflow_id' => $workflow->id,
                    'workflow_version' => $workflow->version,
                    'parallel_rule' => $this->centralApproval->parallelRuleFor($centralConfig),
                    'submitted_at' => ManilaTime::now(),
                    'submitted_by' => $actor->id,
                    'status' => TravelOrderStatus::PendingSupervisor,
                    'current_stage' => LeaveApprovalStage::ImmediateSupervisor,
                ]));
                $order = $existing->fresh();
                $order->assignments()->delete();
            } else {
                $order = TravelOrder::query()->create(array_merge($payload, [
                    'travel_order_number' => $this->nextNumber(),
                    'requester_id' => $requester->id,
                    'department_id' => $requester->department_id,
                    'workflow_id' => $workflow->id,
                    'workflow_version' => $workflow->version,
                    'parallel_rule' => $this->centralApproval->parallelRuleFor($centralConfig),
                    'date_requested' => ManilaTime::now(),
                    'submitted_at' => ManilaTime::now(),
                    'submitted_by' => $actor->id,
                    'status' => TravelOrderStatus::PendingSupervisor,
                    'current_stage' => LeaveApprovalStage::ImmediateSupervisor,
                ]));
            }

            $this->syncPersonnel($order, $travelerIds);
            $this->syncDestinations($order, $data['destinations'] ?? []);
            $this->storeAttachments($order, $actor, $data['attachments'] ?? []);

            $this->centralApproval->bootstrapTravelOrder($order, $requester);
            $meta = $this->centralApproval->initialStageMeta($centralConfig);

            if ($meta['stage'] === null) {
                $now = ManilaTime::now();
                $order->update([
                    'status' => TravelOrderStatus::Approved,
                    'current_stage' => null,
                    'approved_at' => $now,
                    'finalized_by' => $actor->id,
                ]);
                $first = null;
            } else {
                $first = $this->advanceToNextPendingStage($order, null);
                $order->update([
                    'status' => $first?->pendingStatusForTravel() ?? TravelOrderStatus::Approved,
                    'current_stage' => $first,
                    'approved_at' => $first ? null : ManilaTime::now(),
                    'finalized_by' => $first ? null : $actor->id,
                ]);
            }

            $this->recordAction(
                $order,
                $actor,
                $first ?? LeaveApprovalStage::CeoFinalApproval,
                'submitted',
                null,
                null,
                $order->status,
                'Travel order submitted.'
            );
            $this->audit->log($actor, 'travel_order_submitted', 'TravelOrder', $order->id, "{$requester->fullName()} submitted {$order->travel_order_number}.");

            return $order->fresh([
                'requester.department',
                'personnel.employee.department',
                'destinations',
                'assignments.user',
                'actions.user',
            ]);
        });
    }

    public function afterSubmit(TravelOrder $order): void
    {
        $order->loadMissing(['requester.user', 'assignments.user', 'personnel.employee.user']);
        $this->notifier->submitted($order);

        if ($order->current_stage && $order->current_stage !== LeaveApprovalStage::ImmediateSupervisor) {
            $this->notifier->stageReady($order, $order->current_stage);
        }
    }

    public function decide(TravelOrder $order, User $actor, LeaveDecision $decision, string $reason = '', ?string $signature = null): TravelOrder
    {
        if ($actor->employee?->id === $order->requester_id) {
            throw ValidationException::withMessages([
                'decision' => 'You cannot endorse or approve your own travel order request.',
            ]);
        }

        if (! $order->status?->isOpen()) {
            throw ValidationException::withMessages([
                'decision' => 'This travel order is no longer awaiting action.',
            ]);
        }

        $stage = $order->current_stage;
        if (! $stage) {
            throw ValidationException::withMessages(['decision' => 'This travel order has no active approval stage.']);
        }

        if ($stage === LeaveApprovalStage::CeoFinalApproval) {
            $assignedFinal = $order->assignments()
                ->where('stage', LeaveApprovalStage::CeoFinalApproval->value)
                ->where('user_id', $actor->id)
                ->where('status', 'pending')
                ->exists();

            if (! $assignedFinal) {
                throw ValidationException::withMessages([
                    'decision' => 'You are not authorized to perform final approval on this travel order.',
                ]);
            }
        }

        if ($decision === LeaveDecision::Denied && trim($reason) === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when rejecting a travel order.',
            ]);
        }

        $previous = $order->status;

        return DB::transaction(function () use ($order, $actor, $decision, $reason, $signature, $stage, $previous) {
            $assignment = $order->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $assignment || ! $assignment->isPending()) {
                throw ValidationException::withMessages([
                    'decision' => 'You are not an authorized pending endorser for the current stage.',
                ]);
            }

            $assignment->update([
                'status' => $decision->value,
                'reason' => $reason !== '' ? $reason : null,
                'signature' => $signature,
                'acted_at' => ManilaTime::now(),
            ]);

            $order->refresh()->load('assignments');
            $outcome = $this->evaluateStage($order, $stage);

            if ($outcome === 'pending') {
                $this->recordAction($order, $actor, $stage, 'decision', $decision, $previous, $order->status, $reason);
                $this->notifier->decisionToRequester(
                    $order,
                    'Travel order endorsement update',
                    "{$actor->name} {$decision->label()} travel order {$order->travel_order_number}. Parallel endorsement is still in progress.",
                    $decision === LeaveDecision::Denied ? 'warning' : 'info'
                );

                return $order->fresh(['requester.user', 'assignments.user', 'actions.user', 'personnel.employee']);
            }

            if ($outcome === 'denied') {
                $order->update([
                    'status' => TravelOrderStatus::Denied,
                    'current_stage' => $stage,
                ]);
                $this->recordAction($order, $actor, $stage, 'decision', $decision, $previous, TravelOrderStatus::Denied, $reason);
                $this->audit->log($actor, 'travel_order_denied', 'TravelOrder', $order->id, "{$order->travel_order_number} was rejected at {$stage->shortLabel()}.");
                $this->notifier->decisionToRequester($order, 'Travel order rejected', "Travel order {$order->travel_order_number} was rejected.", 'error');
                $this->emailNotifications->travelOrderRejected($order, $actor, $reason !== '' ? $reason : 'No reason was provided.');

                return $order->fresh(['requester.user', 'assignments.user', 'actions.user', 'personnel.employee']);
            }

            $mixed = $outcome === 'approved_mixed';
            $next = $this->advanceToNextPendingStage($order, $stage);

            if ($next) {
                $status = $mixed && $stage->isParallel()
                    ? TravelOrderStatus::PartiallyApproved
                    : $next->pendingStatusForTravel();
                $order->update([
                    'status' => $status,
                    'current_stage' => $next,
                ]);
                $this->recordAction($order, $actor, $stage, 'decision', $decision, $previous, $status, $reason);
                $this->notifier->decisionToRequester(
                    $order,
                    $mixed ? 'Travel order partially endorsed' : 'Travel order endorsement update',
                    "{$stage->shortLabel()} completed for {$order->travel_order_number}. It is now with {$next->shortLabel()}.",
                    $mixed ? 'warning' : 'success'
                );
                $this->notifier->stageReady($order->fresh(['assignments.user', 'requester.user']), $next);
            } else {
                $now = ManilaTime::now();
                $order->update([
                    'status' => TravelOrderStatus::Approved,
                    'current_stage' => $stage,
                    'approved_at' => $now,
                    'finalized_by' => $actor->id,
                ]);
                $this->recordAction($order, $actor, $stage, 'decision', $decision, $previous, TravelOrderStatus::Approved, $reason);
                $this->notifier->approved($order->fresh(['requester.user', 'personnel.employee.user']));
                $this->emailNotifications->travelOrderApproved($order->fresh(['requester.user']), $actor);
            }

            $this->audit->log($actor, 'travel_order_'.$decision->value, 'TravelOrder', $order->id, "{$actor->name} {$decision->value} {$order->travel_order_number}.");

            return $order->fresh(['requester.user', 'assignments.user', 'actions.user', 'personnel.employee']);
        });
    }

    public function cancel(TravelOrder $order, User $actor, string $reason = ''): TravelOrder
    {
        if (! $order->canBeCancelledByRequester() && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'travel_order' => 'This travel order can no longer be cancelled.',
            ]);
        }

        if ($actor->employee?->id !== $order->requester_id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'travel_order' => 'You can only cancel your own travel order requests.',
            ]);
        }

        $previous = $order->status;

        return DB::transaction(function () use ($order, $actor, $reason, $previous) {
            $order->update([
                'status' => TravelOrderStatus::Cancelled,
                'cancelled_at' => ManilaTime::now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason !== '' ? $reason : null,
            ]);

            $this->recordAction(
                $order,
                $actor,
                $order->current_stage ?? LeaveApprovalStage::ImmediateSupervisor,
                'cancelled',
                null,
                $previous,
                TravelOrderStatus::Cancelled,
                $reason
            );
            $this->audit->log($actor, 'travel_order_cancelled', 'TravelOrder', $order->id, "{$actor->name} cancelled {$order->travel_order_number}.");
            $this->notifier->cancelled($order->fresh(['requester.user', 'assignments.user', 'personnel.employee.user']));

            return $order->fresh(['requester.user', 'assignments.user', 'actions.user', 'personnel.employee']);
        });
    }

    public function superAdminCancelApproved(TravelOrder $order, User $actor, string $reason): TravelOrder
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages(['travel_order' => 'Unauthorized.']);
        }

        if ($order->status !== TravelOrderStatus::Approved) {
            throw ValidationException::withMessages([
                'travel_order' => 'Only fully approved travel orders can be cancelled by Super Admin.',
            ]);
        }

        if (trim($reason) === '') {
            throw ValidationException::withMessages(['reason' => 'Cancellation reason is required.']);
        }

        return DB::transaction(function () use ($order, $actor, $reason) {
            $previous = $order->status;
            $order->update([
                'status' => TravelOrderStatus::Cancelled,
                'cancelled_at' => ManilaTime::now(),
                'cancelled_by' => $actor->id,
                'cancel_reason' => $reason,
            ]);

            $this->recordAction(
                $order,
                $actor,
                $order->current_stage ?? LeaveApprovalStage::CeoFinalApproval,
                'admin_cancelled',
                null,
                $previous,
                TravelOrderStatus::Cancelled,
                $reason
            );
            $this->audit->log($actor, 'travel_order_admin_cancelled', 'TravelOrder', $order->id, "Super Admin cancelled {$order->travel_order_number}.");
            $this->notifier->adminCancelled($order->fresh(['requester.user', 'personnel.employee.user']), $reason);

            return $order->fresh(['requester.user', 'assignments.user', 'personnel.employee', 'modificationLogs.modifier']);
        });
    }

    /** @param  array<string, mixed>  $data */
    public function superAdminUpdateApproved(TravelOrder $order, User $actor, array $data, string $editReason): TravelOrder
    {
        if (! $actor->isAdmin()) {
            throw ValidationException::withMessages(['travel_order' => 'Unauthorized.']);
        }

        if ($order->status !== TravelOrderStatus::Approved) {
            throw ValidationException::withMessages([
                'travel_order' => 'Only fully approved travel orders can be edited in full by Super Admin.',
            ]);
        }

        if (trim($editReason) === '') {
            throw ValidationException::withMessages(['edit_reason' => 'Edit reason is required.']);
        }

        $requester = $order->requester;
        $payload = $this->validatedPayload($data, true);
        $travelerIds = $this->resolveTravelerIds($requester, $data);

        if ($travelerIds === []) {
            throw ValidationException::withMessages([
                'traveler_ids' => 'Select at least one traveler.',
            ]);
        }

        $changes = $this->buildChangeLog($order, $payload, $travelerIds, $data['destinations'] ?? []);

        return DB::transaction(function () use ($order, $actor, $payload, $travelerIds, $data, $editReason, $changes) {
            $order->update($payload);
            $this->syncPersonnel($order, $travelerIds);
            $this->syncDestinations($order, $data['destinations'] ?? []);
            $this->storeAttachments($order, $actor, $data['attachments'] ?? []);

            TravelOrderModificationLog::query()->create([
                'travel_order_id' => $order->id,
                'modified_by' => $actor->id,
                'reason' => $editReason,
                'changes' => $changes,
            ]);

            $this->audit->log($actor, 'travel_order_admin_updated', 'TravelOrder', $order->id, "Super Admin updated {$order->travel_order_number}.");
            $this->notifier->adminUpdated($order->fresh(['requester.user', 'personnel.employee.user']));

            return $order->fresh([
                'requester.department',
                'personnel.employee.department',
                'destinations',
                'attachments',
                'modificationLogs.modifier',
            ]);
        });
    }

    public function pendingFor(User $user)
    {
        return TravelOrder::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->where('status', 'pending'))
            ->whereHas('assignments', function ($assignment) use ($user) {
                $assignment->where('user_id', $user->id)
                    ->where('status', 'pending')
                    ->whereColumn('travel_order_approval_assignments.stage', 'travel_orders.current_stage');
            })
            ->with(['requester.department', 'personnel.employee', 'assignments'])
            ->latest('submitted_at');
    }

    public function historyFor(User $user)
    {
        return TravelOrder::query()
            ->whereHas('assignments', fn ($q) => $q->where('user_id', $user->id)->whereNotNull('acted_at'))
            ->with(['requester.department', 'personnel.employee', 'assignments'])
            ->latest('submitted_at');
    }

    public function userCanAct(User $user, TravelOrder $order): bool
    {
        if ($user->employee?->id === $order->requester_id) {
            return false;
        }

        if (! $order->status?->isOpen() || ! $order->current_stage) {
            return false;
        }

        if ($order->current_stage === LeaveApprovalStage::CeoFinalApproval) {
            return $order->assignments()
                ->where('stage', LeaveApprovalStage::CeoFinalApproval->value)
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->exists();
        }

        return $order->assignments()
            ->where('stage', $order->current_stage->value)
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function userIsAssignedApprover(User $user): bool
    {
        return TravelOrderApprovalAssignment::query()->where('user_id', $user->id)->exists();
    }

    public function dashboardCounts(?Employee $employee = null): array
    {
        $base = TravelOrder::query();
        $mine = $employee ? (clone $base)->ownedByRequester($employee) : null;

        return [
            'total' => $mine ? (clone $mine)->count() : (clone $base)->count(),
            'pending_endorsement' => ($mine ? (clone $mine) : (clone $base))
                ->where('status', TravelOrderStatus::PendingSupervisor)->count(),
            'pending_approval' => ($mine ? (clone $mine) : (clone $base))
                ->whereIn('status', [
                    TravelOrderStatus::PendingDepartmentHead,
                    TravelOrderStatus::PendingAdministrativeHead,
                    TravelOrderStatus::PendingCeoFinalApproval,
                ])->count(),
            'approved' => ($mine ? (clone $mine) : (clone $base))
                ->where('status', TravelOrderStatus::Approved)->count(),
            'rejected' => ($mine ? (clone $mine) : (clone $base))
                ->where('status', TravelOrderStatus::Denied)->count(),
            'cancelled' => ($mine ? (clone $mine) : (clone $base))
                ->where('status', TravelOrderStatus::Cancelled)->count(),
        ];
    }

    private function snapshotApprovers(TravelOrder $order, LeaveApprovalWorkflow $workflow, Employee $requester): void
    {
        $workflow->load(['approvers.user.employee']);

        foreach (LeaveApprovalStage::configurable() as $stage) {
            $approvers = $workflow->approvers->where('stage', $stage);
            $sort = 0;

            foreach ($approvers as $row) {
                $user = $row->user;
                if (! $user || $user->employee?->id === $requester->id) {
                    continue;
                }

                $this->createAssignment($order, $stage, $user, $sort++);
            }
        }

        $ceo = $this->ceo->user();
        if ($ceo && $ceo->employee?->id !== $requester->id) {
            $this->createAssignment($order, LeaveApprovalStage::CeoFinalApproval, $ceo, 0);
        }
    }

    private function createAssignment(TravelOrder $order, LeaveApprovalStage $stage, User $user, int $sort): void
    {
        TravelOrderApprovalAssignment::query()->create([
            'travel_order_id' => $order->id,
            'stage' => $stage,
            'user_id' => $user->id,
            'employee_id' => $user->employee?->id,
            'approver_name' => $user->employee?->fullName() ?: $user->name,
            'approver_position' => $user->employee?->position,
            'approver_role' => $user->role?->value,
            'status' => 'pending',
            'sort_order' => $sort,
        ]);
    }

    private function advanceToNextPendingStage(TravelOrder $order, ?LeaveApprovalStage $from): ?LeaveApprovalStage
    {
        $stages = LeaveApprovalStage::sequence();
        $started = $from === null;

        foreach ($stages as $stage) {
            if (! $started) {
                if ($stage === $from) {
                    $started = true;
                }
                continue;
            }

            if ($order->assignments()->where('stage', $stage->value)->count() > 0) {
                return $stage;
            }
        }

        return null;
    }

    /** @return 'pending'|'approved'|'approved_mixed'|'denied' */
    private function evaluateStage(TravelOrder $order, LeaveApprovalStage $stage): string
    {
        $rows = $order->assignments->where('stage', $stage)->values();
        $active = $rows->reject(fn (TravelOrderApprovalAssignment $row) => $row->status === 'skipped');
        $pending = $active->filter->isPending();
        $approved = $active->filter->isApproved();
        $denied = $active->filter->isDenied();
        $total = $active->count();

        if ($total === 0) {
            return 'approved';
        }

        if (! $stage->isParallel()) {
            if ($denied->isNotEmpty()) {
                return 'denied';
            }
            if ($approved->isNotEmpty()) {
                return 'approved';
            }

            return 'pending';
        }

        $rule = $order->parallel_rule ?? LeaveParallelRule::All;

        return match ($rule) {
            LeaveParallelRule::Any => $this->evalAny($approved, $denied, $pending, $total),
            LeaveParallelRule::Majority => $this->evalMajority($approved, $denied, $pending, $total),
            LeaveParallelRule::All => $this->evalAll($approved, $denied, $pending, $total),
        };
    }

    private function evalAll($approved, $denied, $pending, int $total): string
    {
        if ($denied->isNotEmpty()) {
            return 'denied';
        }
        if ($approved->count() === $total) {
            return 'approved';
        }

        return 'pending';
    }

    private function evalAny($approved, $denied, $pending, int $total): string
    {
        if ($approved->isNotEmpty()) {
            return $denied->isNotEmpty() ? 'approved_mixed' : 'approved';
        }
        if ($pending->isEmpty() && $denied->count() === $total) {
            return 'denied';
        }

        return 'pending';
    }

    private function evalMajority($approved, $denied, $pending, int $total): string
    {
        $needed = (int) floor($total / 2) + 1;

        if ($approved->count() >= $needed) {
            return $denied->isNotEmpty() ? 'approved_mixed' : 'approved';
        }
        if ($denied->count() > ($total - $needed)) {
            return 'denied';
        }

        return 'pending';
    }

    private function recordAction(
        TravelOrder $order,
        User $actor,
        LeaveApprovalStage $stage,
        string $action,
        ?LeaveDecision $decision,
        ?TravelOrderStatus $previous,
        TravelOrderStatus $next,
        ?string $reason = null,
        ?string $signature = null
    ): void {
        TravelOrderApprovalAction::query()->create([
            'travel_order_id' => $order->id,
            'assignment_id' => $order->assignments()
                ->where('stage', $stage->value)
                ->where('user_id', $actor->id)
                ->value('id'),
            'user_id' => $actor->id,
            'stage' => $stage,
            'action' => $action,
            'decision' => $decision,
            'previous_status' => $previous,
            'new_status' => $next,
            'reason' => $reason !== '' ? $reason : null,
            'signature' => $signature,
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 1000),
            'acted_at' => ManilaTime::now(),
        ]);
    }

    private function nextNumber(): string
    {
        $year = ManilaTime::now()->year;
        $prefix = "TO-{$year}-";

        return DB::transaction(function () use ($prefix, $year) {
            $latest = TravelOrder::query()
                ->where('travel_order_number', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('travel_order_number')
                ->value('travel_order_number');

            $sequence = $latest ? ((int) substr($latest, -6)) + 1 : 1;

            return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
        });
    }

    /** @param  list<int>  $travelerIds */
    private function syncPersonnel(TravelOrder $order, array $travelerIds): void
    {
        $travelerIds = array_values(array_unique(array_map('intval', $travelerIds)));
        $order->personnel()->whereNotIn('employee_id', $travelerIds)->delete();

        foreach ($travelerIds as $employeeId) {
            $order->personnel()->firstOrCreate(['employee_id' => $employeeId]);
        }
    }

    /** @param  list<string>  $destinations */
    private function syncDestinations(TravelOrder $order, array $destinations): void
    {
        $destinations = collect($destinations)
            ->map(fn ($d) => trim((string) $d))
            ->filter()
            ->values();

        $order->destinations()->delete();
        foreach ($destinations as $index => $destination) {
            $order->destinations()->create([
                'destination' => $destination,
                'sort_order' => $index,
            ]);
        }

        $order->update([
            'destination' => $destinations->implode('; '),
        ]);
    }

    /** @param  list<UploadedFile>  $files */
    private function storeAttachments(TravelOrder $order, User $actor, array $files): void
    {
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile || ! $file->isValid()) {
                continue;
            }

            $mime = $file->getMimeType();
            $allowed = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
            if (! in_array($mime, $allowed, true)) {
                throw ValidationException::withMessages([
                    'attachments' => 'Attachments must be PDF or image files.',
                ]);
            }

            if ($file->getSize() > 5 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'attachments' => 'Each attachment must be 5 MB or smaller.',
                ]);
            }

            $path = $file->store('travel-order-attachments/'.$order->id, 'public');
            TravelOrderAttachment::query()->create([
                'travel_order_id' => $order->id,
                'file_name' => $file->getClientOriginalName(),
                'file_path' => $path,
                'uploaded_by' => $actor->id,
            ]);
        }
    }

    /** @return list<int> */
    private function resolveTravelerIds(Employee $requester, array $data): array
    {
        $ids = collect($data['traveler_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        if (! empty($data['include_requester_as_traveler'])) {
            if (! in_array($requester->id, $ids, true)) {
                $ids[] = $requester->id;
            }
        }

        $valid = Employee::query()->active()->whereIn('id', $ids)->pluck('id')->all();
        if (count($valid) !== count($ids)) {
            throw ValidationException::withMessages([
                'traveler_ids' => 'One or more selected employees are not active or eligible.',
            ]);
        }

        return $valid;
    }

    /** @return array<string, mixed> */
    private function validatedPayload(array $data, bool $strict): array
    {
        if ($strict && empty($data['purpose'])) {
            throw ValidationException::withMessages(['purpose' => 'Purpose of travel is required.']);
        }

        if ($strict && (empty($data['date_start']) || empty($data['date_end']))) {
            throw ValidationException::withMessages(['date_start' => 'Travel dates are required.']);
        }

        if (! empty($data['date_start']) && ! empty($data['date_end']) && $data['date_end'] < $data['date_start']) {
            throw ValidationException::withMessages(['date_end' => 'End date must be on or after the start date.']);
        }

        $transport = filled($data['transportation'] ?? null)
            ? TravelTransportation::from($data['transportation'])
            : null;

        if ($transport === TravelTransportation::Other && $strict && trim((string) ($data['transportation_other'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'transportation_other' => 'Please specify the transportation mode.',
            ]);
        }

        return [
            'official_station' => $data['official_station'] ?? null,
            'number_of_bh' => $data['number_of_bh'] ?? null,
            'date_start' => $data['date_start'] ?? ManilaTime::todayDate(),
            'date_end' => $data['date_end'] ?? ManilaTime::todayDate(),
            'purpose' => $data['purpose'] ?? '',
            'equipment' => $data['equipment'] ?? null,
            'project_name' => $data['project_name'] ?? null,
            'client_company' => $data['client_company'] ?? null,
            'transportation' => $transport?->value,
            'transportation_other' => $data['transportation_other'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'plate_number' => $data['plate_number'] ?? null,
            'remarks' => $data['remarks'] ?? null,
        ];
    }

    private function assertCanCreate(Employee $requester, User $actor): void
    {
        if ($actor->employee?->id !== $requester->id && ! $actor->isAdmin()) {
            throw ValidationException::withMessages([
                'employee' => 'You can only create travel orders from your own employee account.',
            ]);
        }
    }

    private function assertRequesterOwnsDraft(TravelOrder $order, Employee $requester): void
    {
        if ($order->requester_id !== $requester->id) {
            throw ValidationException::withMessages(['travel_order' => 'You can only edit your own travel orders.']);
        }

        if (! $order->canBeEditedByRequester()) {
            throw ValidationException::withMessages(['travel_order' => 'This travel order can no longer be edited.']);
        }
    }

    /** @param  list<int>  $travelerIds
     * @param  list<string>  $destinations
     * @return list<array<string, mixed>>
     */
    private function buildChangeLog(TravelOrder $order, array $payload, array $travelerIds, array $destinations): array
    {
        $changes = [];
        $order->loadMissing(['personnel.employee', 'destinations']);

        foreach ($payload as $field => $newValue) {
            $old = $order->getAttribute($field);
            if ((string) $old !== (string) $newValue) {
                $changes[] = [
                    'field' => $field,
                    'previous' => $old,
                    'updated' => $newValue,
                ];
            }
        }

        $oldTravelers = $order->personnel->map(fn ($p) => $p->employee?->fullName())->filter()->values()->all();
        $newTravelers = Employee::query()->whereIn('id', $travelerIds)->pluck('full_name')->all();
        if ($oldTravelers !== $newTravelers) {
            $changes[] = [
                'field' => 'travelers',
                'previous' => $oldTravelers,
                'updated' => $newTravelers,
            ];
        }

        $oldDest = $order->destinations->pluck('destination')->all();
        $newDest = collect($destinations)->map(fn ($d) => trim((string) $d))->filter()->values()->all();
        if ($oldDest !== $newDest) {
            $changes[] = [
                'field' => 'destinations',
                'previous' => $oldDest,
                'updated' => $newDest,
            ];
        }

        return $changes;
    }
}
