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
            'endorsement_enabled' => ['sometimes', 'boolean'],
            'final_approval_enabled' => ['sometimes', 'boolean'],
            'confirm_auto_approval' => ['sometimes', 'boolean'],
            'endorser_ids' => ['nullable', 'array'],
            'endorser_ids.*' => ['integer', 'exists:employees,id'],
            'final_approver_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $data['endorsement_enabled'] = $request->boolean('endorsement_enabled');
        $data['final_approval_enabled'] = $request->boolean('final_approval_enabled');
        $data['confirm_auto_approval'] = $request->boolean('confirm_auto_approval');

        $this->workflows->update($type, $request->user(), $data);

        return back()->with('success', $type->label().' approval workflow saved.');
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
