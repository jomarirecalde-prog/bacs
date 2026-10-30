@include('partials.central-approval-timeline', [
    'subject' => $correction,
    'submittedDetail' => ($correction->attendance_date?->format('M j, Y') ?? '—').' · '.($correction->employee?->fullName() ?? '—'),
    'emptyApproversMessage' => 'Awaiting review under Settings → Approval Workflow (Pardon / Time Correction).',
])
