<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\EmailNotificationService;
use App\Support\EmailBranding;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailNotificationController extends Controller
{
    public function __construct(private readonly EmailNotificationService $emails) {}

    public function preview(Request $request)
    {
        $template = $request->string('template', 'account_created')->toString();

        $payload = match ($template) {
            'account_created' => $this->emails->sampleAccountPayload(),
            'transaction_approved' => $this->emails->sampleTransactionApprovedPayload(),
            'transaction_rejected' => $this->emails->sampleTransactionRejectedPayload(),
            default => $this->emails->sampleAccountPayload(),
        };

        $view = match ($template) {
            'transaction_approved' => 'mail.transaction-approved',
            'transaction_rejected' => 'mail.transaction-rejected',
            default => 'mail.account-created',
        };

        if ($request->boolean('raw')) {
            return view($view, array_merge(EmailBranding::layoutContext(), ['payload' => $payload]));
        }

        return view('admin.email-notifications.preview', [
            'templates' => [
                'account_created' => 'Account created',
                'transaction_approved' => 'Transaction approved',
                'transaction_rejected' => 'Transaction rejected',
            ],
            'active' => $template,
            'previewUrl' => route('admin.email-notifications.preview', ['template' => $template, 'raw' => 1]),
        ]);
    }

    public function sendTest(Request $request)
    {
        $data = $request->validate([
            'template' => ['required', Rule::in(['account_created', 'transaction_approved', 'transaction_rejected'])],
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->emails->sendPreview($data['template'], $data['email']);

        return back()->with('success', 'Test email sent to '.$data['email'].'.');
    }
}
