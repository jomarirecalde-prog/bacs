@include('partials.central-approval-timeline', [
    'subject' => $order,
    'submittedDetail' => ($order->requestedLabel() ?? '—').' · Requester: '.($order->requester?->fullName() ?? '—'),
])
