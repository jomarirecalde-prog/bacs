<?php

namespace App\Services;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveParallelRule;
use App\Models\Department;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowApprover;
use App\Models\LeaveWorkflowConfigurationHistory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaveWorkflowService
{
    public function __construct(private readonly CeoResolver $ceo) {}

    public function ensureForDepartment(Department $department, ?User $actor = null): LeaveApprovalWorkflow
    {
        $default = LeaveApprovalWorkflow::query()->where('is_default', true)->first();

        return LeaveApprovalWorkflow::query()->firstOrCreate(
            ['department_id' => $department->id],
            [
                'name' => $department->name,
                'parallel_rule' => $default?->parallel_rule ?? LeaveParallelRule::All,
                'is_default' => false,
                'is_active' => false,
                'version' => 1,
                'created_by' => $actor?->id,
                'updated_by' => $actor?->id,
            ]
        );
    }

    /** @return list<string> */
    public function missingRequirements(LeaveApprovalWorkflow $workflow): array
    {
        $missing = [];
        $workflow->loadMissing('approvers');

        foreach (LeaveApprovalStage::configurable() as $stage) {
            if ($workflow->approvers->where('stage', $stage)->isEmpty()) {
                $missing[] = $stage->shortLabel();
            }
        }

        if (! $this->ceo->user()) {
            $missing[] = 'CEO Final Approver';
        }

        return $missing;
    }

    public function isComplete(LeaveApprovalWorkflow $workflow): bool
    {
        return $this->missingRequirements($workflow) === [];
    }

    public function configurationStatus(LeaveApprovalWorkflow $workflow): string
    {
        if (! $workflow->is_active) {
            return $this->isComplete($workflow) ? 'inactive' : 'incomplete';
        }

        return 'active';
    }

    public function statusLabel(LeaveApprovalWorkflow $workflow): string
    {
        return match ($this->configurationStatus($workflow)) {
            'active' => 'Active',
            'inactive' => 'Inactive',
            default => 'Incomplete',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateWorkflow(LeaveApprovalWorkflow $workflow, array $payload, User $actor): LeaveApprovalWorkflow
    {
        return DB::transaction(function () use ($workflow, $payload, $actor) {
            $previous = $this->snapshotConfiguration($workflow);
            $activate = (bool) ($payload['activate'] ?? false);
            $shouldActivate = $activate || ($payload['is_active'] ?? $workflow->is_active);

            $workflow->update([
                'parallel_rule' => $payload['parallel_rule'],
                'updated_by' => $actor->id,
            ]);

            LeaveApprovalWorkflowApprover::query()->where('workflow_id', $workflow->id)->delete();

            foreach (LeaveApprovalStage::configurable() as $stage) {
                $ids = array_values(array_unique($payload['approvers'][$stage->value] ?? []));
                foreach ($ids as $index => $userId) {
                    LeaveApprovalWorkflowApprover::query()->create([
                        'workflow_id' => $workflow->id,
                        'stage' => $stage,
                        'user_id' => $userId,
                        'sort_order' => $index,
                    ]);
                }
            }

            $workflow->refresh()->load(['approvers.user.employee', 'department', 'updatedByUser']);

            if ($shouldActivate) {
                $missing = $this->missingRequirements($workflow);
                if ($missing !== []) {
                    throw new \InvalidArgumentException('Cannot activate: '.implode(', ', $missing).' not assigned.');
                }
            }

            $workflow->update([
                'is_active' => $shouldActivate,
                'version' => $workflow->version + 1,
            ]);

            $this->recordHistory(
                $workflow,
                $activate ? 'activated' : 'updated',
                $previous,
                $this->snapshotConfiguration($workflow->fresh(['approvers'])),
                $activate ? 'Workflow activated.' : 'Workflow configuration updated.',
                $actor
            );

            return $workflow->fresh(['approvers.user.employee', 'department', 'updatedByUser']);
        });
    }

    public function activate(LeaveApprovalWorkflow $workflow, User $actor): LeaveApprovalWorkflow
    {
        $missing = $this->missingRequirements($workflow);
        if ($missing !== []) {
            throw new \InvalidArgumentException('Cannot activate: '.implode(', ', $missing).' not assigned.');
        }

        $previous = $this->snapshotConfiguration($workflow);
        $workflow->update([
            'is_active' => true,
            'version' => $workflow->version + 1,
            'updated_by' => $actor->id,
        ]);

        $this->recordHistory(
            $workflow,
            'activated',
            $previous,
            $this->snapshotConfiguration($workflow->fresh(['approvers'])),
            'Workflow activated.',
            $actor
        );

        return $workflow->fresh(['approvers.user.employee', 'department', 'updatedByUser']);
    }

    public function deactivate(LeaveApprovalWorkflow $workflow, User $actor): LeaveApprovalWorkflow
    {
        $previous = $this->snapshotConfiguration($workflow);
        $workflow->update([
            'is_active' => false,
            'version' => $workflow->version + 1,
            'updated_by' => $actor->id,
        ]);

        $this->recordHistory(
            $workflow,
            'deactivated',
            $previous,
            $this->snapshotConfiguration($workflow->fresh(['approvers'])),
            'Workflow deactivated.',
            $actor
        );

        return $workflow->fresh(['approvers.user.employee', 'department', 'updatedByUser']);
    }

    public function approverSummary(LeaveApprovalWorkflow $workflow, LeaveApprovalStage $stage): string
    {
        $workflow->loadMissing(['approvers.user.employee']);
        $names = $workflow->approvers
            ->where('stage', $stage)
            ->map(fn (LeaveApprovalWorkflowApprover $row) => $row->user?->employee?->fullName() ?: $row->user?->name)
            ->filter()
            ->values();

        if ($stage === LeaveApprovalStage::CeoFinalApproval) {
            return $this->ceo->label();
        }

        if ($names->isEmpty()) {
            return '—';
        }

        if ($names->count() > 2) {
            return $names->take(2)->implode(', ').' +'.($names->count() - 2);
        }

        return $names->implode(', ');
    }

    public function parallelRequirementLabel(LeaveApprovalWorkflow $workflow): string
    {
        $count = $workflow->approvers()->where('stage', LeaveApprovalStage::ImmediateSupervisor)->count();
        if ($count <= 1) {
            return 'Single approver';
        }

        return match ($workflow->parallel_rule) {
            LeaveParallelRule::All => "All {$count} supervisors must approve",
            LeaveParallelRule::Any => 'Any one supervisor can approve',
            LeaveParallelRule::Majority => 'Majority of '.$count.' supervisors must approve',
        };
    }

    /** @return Collection<int, array{id:int,name:string,position:?string,department:?string,role:string}> */
    public function searchEmployees(string $term, int $limit = 20): Collection
    {
        $term = trim($term);
        if ($term === '') {
            return collect();
        }

        return User::query()
            ->with('employee.department')
            ->where('status', 'active')
            ->whereHas('employee')
            ->where(function ($query) use ($term) {
                $query->where('name', 'like', "%{$term}%")
                    ->orWhereHas('employee', function ($employee) use ($term) {
                        $employee->where('full_name', 'like', "%{$term}%")
                            ->orWhere('employee_number', 'like', "%{$term}%")
                            ->orWhere('position', 'like', "%{$term}%")
                            ->orWhereHas('department', fn ($dept) => $dept->where('name', 'like', "%{$term}%"));
                    });
            })
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->employee?->fullName() ?: $user->name,
                'position' => $user->employee?->position,
                'department' => $user->employee?->department?->name,
                'role' => $user->role?->label() ?? 'Employee',
            ]);
    }

    /** @return array<string, mixed> */
    private function snapshotConfiguration(LeaveApprovalWorkflow $workflow): array
    {
        $workflow->loadMissing(['approvers.user.employee']);

        $approvers = [];
        foreach (LeaveApprovalStage::configurable() as $stage) {
            $approvers[$stage->value] = $workflow->approvers
                ->where('stage', $stage)
                ->map(fn (LeaveApprovalWorkflowApprover $row) => [
                    'user_id' => $row->user_id,
                    'name' => $row->user?->employee?->fullName() ?: $row->user?->name,
                ])
                ->values()
                ->all();
        }

        return [
            'parallel_rule' => $workflow->parallel_rule?->value,
            'is_active' => $workflow->is_active,
            'version' => $workflow->version,
            'approvers' => $approvers,
            'ceo' => $this->ceo->label(),
        ];
    }

    /** @param  array<string, mixed>|null  $previous
     * @param  array<string, mixed>|null  $next
     */
    private function recordHistory(
        LeaveApprovalWorkflow $workflow,
        string $action,
        ?array $previous,
        ?array $next,
        string $summary,
        User $actor
    ): void {
        LeaveWorkflowConfigurationHistory::query()->create([
            'workflow_id' => $workflow->id,
            'department_id' => $workflow->department_id,
            'action' => $action,
            'previous_configuration' => $previous,
            'new_configuration' => $next,
            'summary' => $summary,
            'updated_by' => $actor->id,
        ]);
    }
}
