@php
    use App\Enums\ApprovalTransactionType;
@endphp

<div class="space-y-6">
    @include('admin.settings.partials.approval-workflow-section', [
        'sectionId' => 'approval-workflow',
        'sectionTitle' => 'Approval workflow configuration',
        'sectionLead' => 'One workflow per department. The same endorsers and final approvers apply to all request types below.',
        'departmentScopeDescription' => 'Employees here use these approvers for leave, official time, overtime, and travel order requests.',
        'approvalWorkflowConfiguration' => $approvalWorkflowConfiguration,
        'approvalTransactionTypes' => $approvalUnifiedTransactionTypes,
        'approvalDepartments' => $approvalDepartments,
        'employeeSearchUrl' => $employeeSearchUrl,
        'workflowUpdateKey' => 'unified',
        'workflowHistoryKey' => 'unified',
        'showOutOfSyncWarning' => true,
    ])

    @include('admin.settings.partials.approval-workflow-section', [
        'sectionId' => 'pardon-approval-workflow',
        'sectionTitle' => 'Pardon (Time Correction) workflow',
        'sectionLead' => 'Separate from the main approval workflow. Configure endorsers and final approvers for attendance time-correction (pardon) requests only.',
        'departmentScopeDescription' => 'Employees here use these approvers for pardon / time correction requests only.',
        'approvalWorkflowConfiguration' => $pardonApprovalWorkflowConfiguration,
        'approvalTransactionTypes' => [ApprovalTransactionType::Pardon],
        'approvalDepartments' => $approvalDepartments,
        'employeeSearchUrl' => $employeeSearchUrl,
        'workflowUpdateKey' => ApprovalTransactionType::Pardon->value,
        'workflowHistoryKey' => ApprovalTransactionType::Pardon->value,
        'showOutOfSyncWarning' => false,
    ])
</div>
