@include('partials.central-approval-timeline', [
    'subject' => $obRequest,
    'submittedDetail' => (optional($obRequest->submitted_at)?->timezone('Asia/Manila')->format('M j, Y g:i A') ?? '—').' · Requester: '.($obRequest->employee?->fullName() ?? '—'),
])
