@include('partials.central-approval-timeline', [
    'subject' => $application,
    'submittedDetail' => $application->filedLabel().' · '.$application->employee_print_name,
])
