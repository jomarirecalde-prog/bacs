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
        $data = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'endorsement_enabled' => ['sometimes', 'boolean'],
            'secondary_endorsement_enabled' => ['sometimes', 'boolean'],
            'final_approval_enabled' => ['sometimes', 'boolean'],
            'endorser_ids' => ['nullable', 'array'],
            'endorser_ids.*' => ['integer', 'exists:employees,id'],
            'secondary_endorser_ids' => ['nullable', 'array'],
            'secondary_endorser_ids.*' => ['integer', 'exists:employees,id'],
            'final_approver_id' => ['nullable', 'integer', 'exists:employees,id'],
            'endorsement_mode' => ['required', 'in:sequential,parallel'],
            'endorsement_parallel_rule' => ['required', 'in:all,any,majority'],
            'endorser_active' => ['nullable', 'array'],
            'endorser_active.*' => ['boolean'],
            'secondary_endorser_active' => ['nullable', 'array'],
            'secondary_endorser_active.*' => ['boolean'],
        ]);

        $data['endorsement_enabled'] = $request->boolean('endorsement_enabled');
        $data['secondary_endorsement_enabled'] = $request->boolean('secondary_endorsement_enabled');
        $data['final_approval_enabled'] = $request->boolean('final_approval_enabled');

        if ($transactionType === 'unified') {
            $result = $this->workflows->updateUnified($request->user(), $data);
            $message = 'Unified approval workflow saved for all transaction types.';
        } else {
            $type = ApprovalTransactionType::from($transactionType);
            $result = $this->workflows->update($type, $request->user(), $data);
            $message = $type->label().' approval workflow saved.';
        }

        $reassignment = $result['reassignment'];
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
        if ($transactionType === 'unified') {
            $configIds = $this->workflows->allConfigurations()->pluck('id');

            return view('admin.settings.approval-workflow-history', [
                'type' => null,
                'typeLabel' => 'All transactions',
                'histories' => \App\Models\ApprovalWorkflowConfigurationHistory::query()
                    ->whereIn('workflow_configuration_id', $configIds)
                    ->with(['updater', 'configuration'])
                    ->latest()
                    ->paginate(20),
            ]);
        }

        $type = ApprovalTransactionType::from($transactionType);
        $config = $this->workflows->configurationFor($type);

        return view('admin.settings.approval-workflow-history', [
            'type' => $type,
            'typeLabel' => $type->label(),
            'histories' => $config->histories()->with('updater')->paginate(20),
        ]);
    }
}
