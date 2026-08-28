<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveApprovalStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateLeaveWorkflowRequest;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalWorkflow;
use App\Models\LeaveApprovalWorkflowApprover;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class LeaveWorkflowController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function edit()
    {
        $this->authorize('configure', LeaveApplication::class);

        $this->ensureDepartmentWorkflows();

        $workflows = LeaveApprovalWorkflow::query()
            ->with(['department', 'approvers.user.employee'])
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        $users = User::query()
            ->with('employee.department')
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.leave.workflow', [
            'workflows' => $workflows,
            'users' => $users,
            'stages' => LeaveApprovalStage::cases(),
        ]);
    }

    public function update(UpdateLeaveWorkflowRequest $request)
    {
        $this->authorize('configure', LeaveApplication::class);

        DB::transaction(function () use ($request) {
            foreach ($request->validated('workflows') as $payload) {
                $workflow = LeaveApprovalWorkflow::query()->findOrFail($payload['id']);
                $workflow->update(['parallel_rule' => $payload['parallel_rule']]);

                LeaveApprovalWorkflowApprover::query()->where('workflow_id', $workflow->id)->delete();

                foreach (LeaveApprovalStage::cases() as $stage) {
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
            }
        });

        $this->audit->log($request->user(), 'leave_workflow_updated', 'Leave', null, 'Leave approval workflows were updated.');

        return back()->with('success', 'Leave approval configuration saved.');
    }

    private function ensureDepartmentWorkflows(): void
    {
        $default = LeaveApprovalWorkflow::query()->where('is_default', true)->first();

        Department::query()->active()->ordered()->each(function (Department $department) use ($default) {
            LeaveApprovalWorkflow::query()->firstOrCreate(
                ['department_id' => $department->id],
                [
                    'name' => $department->name,
                    'parallel_rule' => $default?->parallel_rule ?? \App\Enums\LeaveParallelRule::All,
                    'is_default' => false,
                    'is_active' => true,
                ]
            );
        });
    }
}
