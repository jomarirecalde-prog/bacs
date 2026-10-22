<?php

namespace App\Services;

use App\Enums\EndorsementMode;
use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveDecision;
use App\Enums\LeaveParallelRule;
use App\Models\ApprovalWorkflowConfiguration;
use App\Models\ApprovalWorkflowDepartmentSetting;
use Illuminate\Support\Collection;

class EndorsementWorkflowCoordinator
{
    public function settingsFor(ApprovalWorkflowConfiguration $config, ?int $departmentId): ApprovalWorkflowDepartmentSetting
    {
        if ($departmentId === null) {
            return new ApprovalWorkflowDepartmentSetting([
                'endorsement_mode' => EndorsementMode::Parallel,
                'endorsement_parallel_rule' => LeaveParallelRule::All,
            ]);
        }

        return ApprovalWorkflowDepartmentSetting::query()->firstOrCreate(
            [
                'workflow_configuration_id' => $config->id,
                'department_id' => $departmentId,
            ],
            [
                'endorsement_mode' => EndorsementMode::Parallel,
                'endorsement_parallel_rule' => LeaveParallelRule::All,
            ]
        );
    }

    public function initialAssignmentStatus(EndorsementMode $mode, int $sortIndex): string
    {
        if ($mode === EndorsementMode::Sequential) {
            return $sortIndex === 0 ? 'pending' : 'waiting';
        }

        return 'pending';
    }

    /** @param  Collection<int, object>  $rows */
    public function evaluateEndorsementStage(
        Collection $rows,
        EndorsementMode $mode,
        LeaveParallelRule $parallelRule,
    ): string {
        $active = $rows->reject(fn ($row) => in_array($row->status, ['skipped'], true));
        $pending = $active->filter(fn ($row) => $row->isPending());
        $waiting = $active->filter(fn ($row) => method_exists($row, 'isWaiting') && $row->isWaiting());
        $approved = $active->filter(fn ($row) => $row->isApproved());
        $denied = $active->filter(fn ($row) => $row->isDenied());
        $total = $active->count();

        if ($total === 0) {
            return 'approved';
        }

        if ($denied->isNotEmpty()) {
            return 'denied';
        }

        if ($mode === EndorsementMode::Sequential) {
            if ($pending->isNotEmpty() || $waiting->isNotEmpty()) {
                return 'pending';
            }

            return $approved->count() === $total ? 'approved' : 'pending';
        }

        return match ($parallelRule) {
            LeaveParallelRule::Any => $this->evalAny($approved, $denied, $pending, $total),
            LeaveParallelRule::Majority => $this->evalMajority($approved, $denied, $pending, $total),
            LeaveParallelRule::All => $this->evalAll($approved, $denied, $pending, $total),
        };
    }

    /**
     * @param  iterable<object>  $assignments
     * @return object|null Next assignment promoted to pending
     */
    public function promoteNextSequentialEndorser(iterable $assignments, LeaveApprovalStage $stage): ?object
    {
        $rows = collect($assignments)
            ->filter(fn ($row) => ($row->stage?->value ?? $row->stage) === $stage->value)
            ->sortBy('sort_order')
            ->values();

        if ($rows->contains(fn ($row) => $row->isPending())) {
            return null;
        }

        $next = $rows->first(fn ($row) => method_exists($row, 'isWaiting') && $row->isWaiting());
        if (! $next) {
            return null;
        }

        $next->update(['status' => 'pending']);

        return $next->fresh();
    }

    public function endorsementUsesParallelStage(EndorsementMode $mode): bool
    {
        return $mode === EndorsementMode::Parallel;
    }

    private function evalAll($approved, $denied, $pending, int $total): string
    {
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
        $threshold = (int) floor($total / 2) + 1;

        if ($approved->count() >= $threshold) {
            return 'approved';
        }

        if ($denied->count() >= $threshold) {
            return 'denied';
        }

        return 'pending';
    }

    public function syncDepartmentSettings(
        ApprovalWorkflowConfiguration $config,
        int $departmentId,
        EndorsementMode $mode,
        LeaveParallelRule $parallelRule,
    ): ApprovalWorkflowDepartmentSetting {
        return ApprovalWorkflowDepartmentSetting::query()->updateOrCreate(
            [
                'workflow_configuration_id' => $config->id,
                'department_id' => $departmentId,
            ],
            [
                'endorsement_mode' => $mode,
                'endorsement_parallel_rule' => $parallelRule,
            ]
        );
    }

    /** @return array{endorsement_mode: EndorsementMode, parallel_rule: LeaveParallelRule} */
    public function snapshotForRequest(ApprovalWorkflowConfiguration $config, ?int $departmentId): array
    {
        $settings = $this->settingsFor($config, $departmentId);

        return [
            'endorsement_mode' => $settings->endorsement_mode ?? EndorsementMode::Parallel,
            'parallel_rule' => $settings->endorsement_parallel_rule ?? LeaveParallelRule::All,
        ];
    }
}
