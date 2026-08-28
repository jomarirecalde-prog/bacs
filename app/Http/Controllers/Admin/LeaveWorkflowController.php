<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LeaveApprovalStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateDepartmentLeaveWorkflowRequest;
use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveApprovalWorkflow;
use App\Services\AuditLogger;
use App\Services\CeoResolver;
use App\Services\LeaveWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LeaveWorkflowController extends Controller
{
    public function __construct(
        private readonly LeaveWorkflowService $workflows,
        private readonly CeoResolver $ceo,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('configure', LeaveApplication::class);

        $query = Department::query()->active()->ordered()->withCount('employees');

        if ($search = trim((string) $request->query('q', ''))) {
            $query->where('name', 'like', "%{$search}%");
        }

        $departments = $query->paginate(20)->withQueryString();

        $workflows = LeaveApprovalWorkflow::query()
            ->with(['approvers.user.employee', 'updatedByUser'])
            ->whereIn('department_id', $departments->pluck('id'))
            ->get()
            ->keyBy('department_id');

        foreach ($departments as $department) {
            if (! $workflows->has($department->id)) {
                $workflows->put($department->id, $this->workflows->ensureForDepartment($department, $request->user()));
            }
        }

        return view('admin.leave.configuration.index', [
            'departments' => $departments,
            'workflows' => $workflows,
            'ceoLabel' => $this->ceo->label(),
            'workflowService' => $this->workflows,
            'filters' => [
                'q' => $search ?? '',
                'status' => $request->query('status', ''),
            ],
        ]);
    }

    public function show(Department $department)
    {
        $this->authorize('configure', LeaveApplication::class);

        $workflow = $this->workflows->ensureForDepartment($department, request()->user());
        $workflow->load(['approvers.user.employee', 'updatedByUser', 'department']);

        $selected = [];
        foreach (LeaveApprovalStage::configurable() as $stage) {
            $selected[$stage->value] = $workflow->approvers
                ->where('stage', $stage)
                ->map(fn ($row) => [
                    'id' => $row->user_id,
                    'name' => $row->user?->employee?->fullName() ?: $row->user?->name,
                    'position' => $row->user?->employee?->position,
                    'department' => $row->user?->employee?->department?->name,
                ])
                ->values()
                ->all();
        }

        return view('admin.leave.configuration.show', [
            'department' => $department->loadCount('employees'),
            'workflow' => $workflow,
            'stages' => LeaveApprovalStage::configurable(),
            'selected' => $selected,
            'missing' => $this->workflows->missingRequirements($workflow),
            'ceoLabel' => $this->ceo->label(),
            'ceoUser' => $this->ceo->user(),
            'parallelLabel' => $this->workflows->parallelRequirementLabel($workflow),
        ]);
    }

    public function update(UpdateDepartmentLeaveWorkflowRequest $request, Department $department)
    {
        $this->authorize('configure', LeaveApplication::class);

        $workflow = $this->workflows->ensureForDepartment($department, $request->user());

        try {
            $this->workflows->updateWorkflow($workflow, $request->validated(), $request->user());
        } catch (\InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['activate' => $exception->getMessage()]);
        }

        $this->audit->log($request->user(), 'leave_workflow_updated', 'Leave', $workflow->id, "Leave approval workflow updated for {$department->name}.");

        return redirect()
            ->route('admin.leave.workflow.show', $department)
            ->with('success', 'Department leave approval configuration saved.');
    }

    public function activate(Request $request, Department $department)
    {
        $this->authorize('configure', LeaveApplication::class);

        $workflow = $this->workflows->ensureForDepartment($department, $request->user());
        $missing = $this->workflows->missingRequirements($workflow);

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'workflow' => 'Cannot activate this workflow. '.implode(', ', $missing).' not assigned.',
            ]);
        }

        $this->workflows->activate($workflow, $request->user());
        $this->audit->log($request->user(), 'leave_workflow_activated', 'Leave', $workflow->id, "Activated leave workflow for {$department->name}.");

        return back()->with('success', 'Workflow activated.');
    }

    public function deactivate(Request $request, Department $department)
    {
        $this->authorize('configure', LeaveApplication::class);

        $workflow = $this->workflows->ensureForDepartment($department, $request->user());
        $this->workflows->deactivate($workflow, $request->user());
        $this->audit->log($request->user(), 'leave_workflow_deactivated', 'Leave', $workflow->id, "Deactivated leave workflow for {$department->name}.");

        return back()->with('success', 'Workflow deactivated.');
    }

    public function history(Department $department)
    {
        $this->authorize('configure', LeaveApplication::class);

        $workflow = $this->workflows->ensureForDepartment($department, request()->user());

        $histories = $workflow->configurationHistories()
            ->with('updatedByUser')
            ->paginate(25);

        return view('admin.leave.configuration.history', [
            'department' => $department,
            'workflow' => $workflow,
            'histories' => $histories,
        ]);
    }

    public function searchEmployees(Request $request)
    {
        $this->authorize('configure', LeaveApplication::class);

        $term = (string) $request->query('q', '');

        return response()->json([
            'results' => $this->workflows->searchEmployees($term),
        ]);
    }
}
