<?php

namespace Database\Seeders;

use App\Enums\LeaveApprovalStage;
use App\Enums\LeaveParallelRule;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowApprover;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeaveWorkflowSeeder extends Seeder
{
    public function run(): void
    {
        $default = LeaveApprovalWorkflow::query()->where('is_default', true)->first();
        if ($default) {
            $default->update(['parallel_rule' => LeaveParallelRule::All]);
            $this->syncStage($default, LeaveApprovalStage::AdministrativeHead, $this->usersByPosition(['Chief Administrative', 'Admin Manager']));
        }

        $ceo = Employee::query()
            ->where('position', 'like', '%CEO%')
            ->with('user')
            ->first()
            ?->user;

        if ($ceo) {
            Setting::put('ceo_user_id', (string) $ceo->id);
        }

        Department::query()->active()->ordered()->each(function (Department $department) use ($default) {
            $workflow = LeaveApprovalWorkflow::query()->firstOrCreate(
                ['department_id' => $department->id],
                [
                    'name' => $department->name,
                    'parallel_rule' => $default?->parallel_rule ?? LeaveParallelRule::All,
                    'is_default' => false,
                    'is_active' => false,
                    'version' => 1,
                ]
            );

            $leaders = Employee::query()
                ->where('department_id', $department->id)
                ->where(function ($q) {
                    $q->where('position', 'like', '%Team Leader%')
                        ->orWhere('position', 'like', '%Supervisor%')
                        ->orWhere('position', 'like', '%Head%')
                        ->orWhere('position', 'like', '%Manager%');
                })
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            $this->syncStage($workflow, LeaveApprovalStage::ImmediateSupervisor, $leaders);
            $this->syncStage($workflow, LeaveApprovalStage::DepartmentHead, $leaders->take(1));
            $this->syncStage($workflow, LeaveApprovalStage::AdministrativeHead, $this->usersByPosition(['Chief Administrative', 'Admin Manager']));
        });
    }

    private function usersByPosition(array $needles)
    {
        return Employee::query()
            ->where(function ($q) use ($needles) {
                foreach ($needles as $needle) {
                    $q->orWhere('position', 'like', '%'.$needle.'%');
                }
            })
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();
    }

    private function syncStage(LeaveApprovalWorkflow $workflow, LeaveApprovalStage $stage, $users): void
    {
        LeaveApprovalWorkflowApprover::query()
            ->where('workflow_id', $workflow->id)
            ->where('stage', $stage->value)
            ->delete();

        $sort = 0;
        foreach ($users as $user) {
            if (! $user instanceof User) {
                continue;
            }

            LeaveApprovalWorkflowApprover::query()->create([
                'workflow_id' => $workflow->id,
                'stage' => $stage,
                'user_id' => $user->id,
                'sort_order' => $sort++,
            ]);
        }
    }
}
