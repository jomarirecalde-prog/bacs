<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalTransactionType;
use App\Http\Controllers\Controller;
use App\Services\CentralApprovalWorkflowService;
use Illuminate\Http\Request;

class ApprovalWorkflowController extends Controller
{
    public function __construct(private readonly CentralApprovalWorkflowService $workflows) {}

    public function searchEmployees(Request $request)
    {
        return response()->json([
            'results' => $this->workflows->searchEmployees((string) $request->query('q', '')),
        ]);
    }

    public function update(Request $request, string $transactionType)
    {
        $type = ApprovalTransactionType::from($transactionType);

        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'endorsement_enabled' => ['sometimes', 'boolean'],
            'final_approval_enabled' => ['sometimes', 'boolean'],
            'confirm_auto_approval' => ['sometimes', 'boolean'],
            'endorser_ids' => ['nullable', 'array'],
            'endorser_ids.*' => ['integer', 'exists:employees,id'],
            'final_approver_id' => ['nullable', 'integer', 'exists:employees,id'],
            'endorsement_mode' => ['required', 'in:sequential,parallel'],
            'endorsement_parallel_rule' => ['required', 'in:all,any,majority'],
            'endorser_active' => ['nullable', 'array'],
            'endorser_active.*' => ['boolean'],
            'confirm_workflow_change' => ['sometimes', 'boolean'],
            'confirm_reassignment' => ['sometimes', 'boolean'],
        ]);

        $data['endorsement_enabled'] = $request->boolean('endorsement_enabled');
        $data['final_approval_enabled'] = $request->boolean('final_approval_enabled');
        $data['confirm_auto_approval'] = $request->boolean('confirm_auto_approval');
        $data['confirm_workflow_change'] = $request->boolean('confirm_workflow_change');
        $data['confirm_reassignment'] = $request->boolean('confirm_reassignment');

        $result = $this->workflows->update($type, $request->user(), $data);
        $reassignment = $result['reassignment'];

        $message = $type->label().' approval workflow saved.';
        if (($reassignment['total'] ?? 0) > 0) {
            $message .= ' Reassigned '.$reassignment['total'].' pending task(s) across '.$reassignment['transactions'].' transaction(s).';
        }

        return back()
            ->with('success', $message)
            ->with('reassignment_result', $reassignment);
    }

    public function previewReassignment(Request $request, string $transactionType)
    {
        $type = ApprovalTransactionType::from($transactionType);

        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'endorser_ids' => ['nullable', 'array'],
            'endorser_ids.*' => ['integer', 'exists:employees,id'],
            'final_approver_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $preview = $this->workflows->previewReassignment($type, (int) $data['department_id'], $data);

        return response()->json([
            'endorsement_tasks' => $preview['endorsement_tasks'],
            'final_tasks' => $preview['final_tasks'],
            'total' => $preview['total'],
        ]);
    }

    public function reassignmentHistory(string $transactionType)
    {
        $type = ApprovalTransactionType::from($transactionType);
        $config = $this->workflows->configurationFor($type);

        $logs = \App\Models\ApprovalWorkflowReassignmentLog::query()
            ->where('workflow_configuration_id', $config->id)
            ->with(['initiator', 'previousUser', 'newUser', 'department'])
            ->latest()
            ->paginate(25);

        return view('admin.settings.approval-workflow-reassignments', [
            'type' => $type,
            'logs' => $logs,
        ]);
    }

    public function history(string $transactionType)
    {
        $type = ApprovalTransactionType::from($transactionType);
        $config = $this->workflows->configurationFor($type);

        return view('admin.settings.approval-workflow-history', [
            'type' => $type,
            'histories' => $config->histories()->with('updater')->paginate(20),
        ]);
    }
}
