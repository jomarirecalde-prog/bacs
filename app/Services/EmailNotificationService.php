<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\EmailNotificationType;
use App\Enums\UserRole;
use App\Enums\TravelOrderStatus;
use App\Mail\AccountCreatedMail;
use App\Mail\AccountUpdatedMail;
use App\Mail\TransactionApprovedMail;
use App\Mail\TransactionRejectedMail;
use App\Models\AttendanceCorrectionRequest;
use App\Models\EmailNotificationLog;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\TravelOrder;
use App\Models\User;
use App\Support\ManilaTime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailNotificationService
{
    public function afterCommit(callable $callback): void
    {
        if (DB::transactionLevel() > 0) {
            DB::afterCommit($callback);

            return;
        }

        $callback();
    }

    public function accountCreated(User $user, Employee $employee): void
    {
        $this->afterCommit(function () use ($user, $employee) {
            $status = $user->status instanceof AccountStatus
                ? $user->status->label()
                : (string) $user->status;

            $setupUrl = app(AccountPasswordSetupService::class)->signedSetupUrl($user);

            $payload = [
                'greeting_name' => $employee->first_name ?: $user->name,
                'employee_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'username' => $user->username,
                'email' => $user->email,
                'registered_at' => $employee->created_at?->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A') ?? now()->format('F j, Y g:i A'),
                'account_status' => $status,
                'status_badge' => 'account_created',
                'status_label' => strtoupper($status === 'Active' ? 'ACCOUNT CREATED' : $status),
                'cta_label' => 'SET YOUR PASSWORD',
                'cta_url' => $setupUrl,
                'setup_url' => $setupUrl,
            ];

            $this->deliver(
                type: EmailNotificationType::AccountCreated,
                recipient: $user->email,
                subject: 'BACS | Account Successfully Created',
                mailable: new AccountCreatedMail($payload),
                dedupeKey: 'account_created:user:'.$user->id,
                userId: $user->id,
                notifiable: $employee,
                meta: ['employee_id' => $employee->id],
            );
        });
    }

    public function accountAccessUpdated(User $user, Employee $employee, bool $passwordReset = false): void
    {
        $this->afterCommit(function () use ($user, $employee, $passwordReset) {
            $user->refresh();

            $status = $user->status instanceof AccountStatus
                ? $user->status->label()
                : (string) $user->status;

            $role = $user->role instanceof UserRole
                ? $user->role->label()
                : (string) $user->role;

            $setupUrl = $passwordReset && $user->must_change_password
                ? app(AccountPasswordSetupService::class)->signedSetupUrl($user)
                : null;

            $payload = [
                'greeting_name' => $employee->first_name ?: $user->name,
                'employee_name' => $employee->fullName(),
                'employee_number' => $employee->employee_number,
                'username' => $user->username,
                'email' => $user->email,
                'updated_at' => now()->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A'),
                'role' => $role,
                'account_status' => $status,
                'status_badge' => 'account_created',
                'status_label' => 'ACCOUNT ACCESS UPDATED',
                'password_reset' => $passwordReset,
                'setup_url' => $setupUrl,
                'cta_label' => $setupUrl ? 'SET YOUR NEW PASSWORD' : 'LOGIN TO BACS SYSTEM',
                'cta_url' => $setupUrl ?: route('login'),
            ];

            $this->deliver(
                type: EmailNotificationType::AccountUpdated,
                recipient: $user->email,
                subject: 'BACS | Account Access Updated',
                mailable: new AccountUpdatedMail($payload),
                dedupeKey: 'account_updated:user:'.$user->id.':'.now()->timestamp,
                userId: $user->id,
                notifiable: $employee,
                meta: ['employee_id' => $employee->id],
            );
        });
    }

    public function leaveApproved(LeaveApplication $application, User $approver, string $statusLabel, string $dedupeKey): void
    {
        $application->loadMissing(['employee.user']);

        $employee = $application->employee;
        $user = $employee?->user;
        if (! $user?->email) {
            return;
        }

        $this->afterCommit(function () use ($application, $approver, $statusLabel, $dedupeKey, $user) {
            $payload = $this->leaveTransactionPayload(
                $application,
                $user,
                $approver,
                $statusLabel,
                'approved',
            );

            $this->deliver(
                type: EmailNotificationType::TransactionApproved,
                recipient: $user->email,
                subject: 'BACS | Transaction Approved - '.$application->application_number,
                mailable: new TransactionApprovedMail($payload),
                dedupeKey: $dedupeKey,
                userId: $user->id,
                notifiable: $application,
                meta: ['leave_application_id' => $application->id],
            );
        });
    }

    public function leaveRejected(LeaveApplication $application, User $approver, string $reason, string $dedupeKey): void
    {
        $application->loadMissing(['employee.user']);

        $employee = $application->employee;
        $user = $employee?->user;
        if (! $user?->email) {
            return;
        }

        $this->afterCommit(function () use ($application, $approver, $reason, $dedupeKey, $user) {
            $payload = $this->leaveTransactionPayload(
                $application,
                $user,
                $approver,
                $application->status?->label() ?? 'Rejected',
                'rejected',
                $reason,
            );

            $this->deliver(
                type: EmailNotificationType::TransactionRejected,
                recipient: $user->email,
                subject: 'BACS | Transaction Rejected - '.$application->application_number,
                mailable: new TransactionRejectedMail($payload),
                dedupeKey: $dedupeKey,
                userId: $user->id,
                notifiable: $application,
                meta: ['leave_application_id' => $application->id],
            );
        });
    }

    public function travelOrderApproved(TravelOrder $order, User $approver): void
    {
        $order->loadMissing(['requester.user']);
        $user = $order->requester?->user;
        if (! $user?->email) {
            return;
        }

        $this->afterCommit(function () use ($order, $approver, $user) {
            $payload = $this->travelTransactionPayload(
                $order,
                $user,
                $approver,
                TravelOrderStatus::Approved->label(),
                'approved',
            );

            $this->deliver(
                type: EmailNotificationType::TransactionApproved,
                recipient: $user->email,
                subject: 'BACS | Travel Order Approved - '.$order->travel_order_number,
                mailable: new TransactionApprovedMail($payload),
                dedupeKey: 'travel:'.$order->id.':approved',
                userId: $user->id,
                notifiable: $order,
                meta: ['travel_order_id' => $order->id],
            );
        });
    }

    public function travelOrderRejected(TravelOrder $order, User $approver, string $reason): void
    {
        $order->loadMissing(['requester.user']);
        $user = $order->requester?->user;
        if (! $user?->email) {
            return;
        }

        $this->afterCommit(function () use ($order, $approver, $reason, $user) {
            $payload = $this->travelTransactionPayload(
                $order,
                $user,
                $approver,
                TravelOrderStatus::Denied->label(),
                'rejected',
                $reason,
            );

            $this->deliver(
                type: EmailNotificationType::TransactionRejected,
                recipient: $user->email,
                subject: 'BACS | Travel Order Rejected - '.$order->travel_order_number,
                mailable: new TransactionRejectedMail($payload),
                dedupeKey: 'travel:'.$order->id.':rejected',
                userId: $user->id,
                notifiable: $order,
                meta: ['travel_order_id' => $order->id],
            );
        });
    }

    public function correctionApproved(AttendanceCorrectionRequest $request, User $reviewer): void
    {
        $request->loadMissing(['employee.user']);
        $user = $request->employee?->user;
        if (! $user?->email) {
            return;
        }

        $this->afterCommit(function () use ($request, $reviewer, $user) {
            $reference = $this->correctionReference($request);
            $payload = $this->correctionPayload(
                $request,
                $user,
                $reviewer,
                'Approved',
                'approved',
                $reference,
            );

            $this->deliver(
                type: EmailNotificationType::TransactionApproved,
                recipient: $user->email,
                subject: 'BACS | Transaction Approved - '.$reference,
                mailable: new TransactionApprovedMail($payload),
                dedupeKey: 'correction:'.$request->id.':approved',
                userId: $user->id,
                notifiable: $request,
                meta: ['attendance_correction_id' => $request->id],
            );
        });
    }

    public function correctionRejected(AttendanceCorrectionRequest $request, User $reviewer, ?string $remarks): void
    {
        $request->loadMissing(['employee.user']);
        $user = $request->employee?->user;
        if (! $user?->email) {
            return;
        }

        $this->afterCommit(function () use ($request, $reviewer, $remarks, $user) {
            $reference = $this->correctionReference($request);
            $reason = filled($remarks) ? $remarks : 'No additional remarks were provided.';
            $payload = $this->correctionPayload(
                $request,
                $user,
                $reviewer,
                'Rejected',
                'rejected',
                $reference,
                $reason,
            );

            $this->deliver(
                type: EmailNotificationType::TransactionRejected,
                recipient: $user->email,
                subject: 'BACS | Transaction Rejected - '.$reference,
                mailable: new TransactionRejectedMail($payload),
                dedupeKey: 'correction:'.$request->id.':rejected',
                userId: $user->id,
                notifiable: $request,
                meta: ['attendance_correction_id' => $request->id],
            );
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleAccountPayload(): array
    {
        return [
            'greeting_name' => 'Juan',
            'employee_name' => 'Dela Cruz, Juan M.',
            'employee_number' => 'BACS-2026-0099',
            'username' => 'juan.delacruz',
            'email' => 'employee@example.com',
            'registered_at' => now()->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A'),
            'account_status' => 'Active',
            'status_badge' => 'account_created',
            'status_label' => 'ACCOUNT CREATED',
            'cta_label' => 'SET YOUR PASSWORD',
            'cta_url' => route('login').'#sample-setup-link',
            'setup_url' => route('login').'#sample-setup-link',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleTransactionApprovedPayload(): array
    {
        return [
            'greeting_name' => 'Juan',
            'title' => 'Transaction Approved',
            'intro' => 'Your transaction has been approved by an authorized approver.',
            'reference_number' => 'LV-2026-00042',
            'transaction_type' => 'Leave Application',
            'description' => 'Vacation Leave · Mar 10–12, 2026 · 3 day(s)',
            'action_at' => now()->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A'),
            'actor_name' => 'Maria Santos',
            'status_label' => 'APPROVED',
            'status_badge' => 'approved',
            'cta_label' => 'VIEW TRANSACTION',
            'cta_url' => route('login'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleTransactionRejectedPayload(): array
    {
        return [
            'greeting_name' => 'Juan',
            'title' => 'Transaction Rejected',
            'intro' => 'Your transaction was reviewed and could not be approved.',
            'reference_number' => 'DTR-CR-00018',
            'transaction_type' => 'DTR Correction Request',
            'description' => 'Time Out correction · Feb 14, 2026',
            'action_at' => now()->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A'),
            'actor_name' => 'Admin User',
            'status_label' => 'REJECTED',
            'status_badge' => 'rejected',
            'rejection_reason' => 'Submitted time does not match supporting records.',
            'cta_label' => 'VIEW TRANSACTION',
            'cta_url' => route('login'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function sendPreview(string $template, string $recipient): void
    {
        $payload = match ($template) {
            'account_created' => $this->sampleAccountPayload(),
            'transaction_approved' => $this->sampleTransactionApprovedPayload(),
            'transaction_rejected' => $this->sampleTransactionRejectedPayload(),
            default => throw new \InvalidArgumentException('Unknown email template.'),
        };

        $mailable = match ($template) {
            'account_created' => new AccountCreatedMail($payload),
            'transaction_approved' => new TransactionApprovedMail($payload),
            'transaction_rejected' => new TransactionRejectedMail($payload),
        };

        Mail::to($recipient)->send($mailable);
    }

    private function correctionReference(AttendanceCorrectionRequest $request): string
    {
        return 'DTR-CR-'.str_pad((string) $request->id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function correctionPayload(
        AttendanceCorrectionRequest $request,
        User $recipient,
        User $actor,
        string $statusLabel,
        string $badge,
        string $reference,
        ?string $rejectionReason = null,
    ): array {
        $date = $request->attendance_date->format('F j, Y');
        $description = $request->punchLabel().' correction · '.$date;

        $payload = [
            'greeting_name' => $recipient->employee?->first_name ?: $recipient->name,
            'title' => $badge === 'approved' ? 'Transaction Approved' : 'Transaction Rejected',
            'intro' => $badge === 'approved'
                ? 'Your DTR correction request has been approved.'
                : 'Your DTR correction request was not approved.',
            'reference_number' => $reference,
            'transaction_type' => 'DTR Correction Request',
            'description' => $description,
            'action_at' => ($request->reviewed_at ?? ManilaTime::now())->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A'),
            'actor_name' => $actor->name,
            'status_label' => strtoupper($statusLabel),
            'status_badge' => $badge,
            'cta_label' => 'VIEW TRANSACTION',
            'cta_url' => route('employee.attendance-corrections.show', $request),
        ];

        if ($rejectionReason !== null) {
            $payload['rejection_reason'] = $rejectionReason;
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    private function travelTransactionPayload(
        TravelOrder $order,
        User $recipient,
        User $approver,
        string $statusLabel,
        string $badge,
        ?string $rejectionReason = null,
    ): array {
        $description = $order->dateRangeLabel().' · '.$order->destination;

        $payload = [
            'greeting_name' => $recipient->employee?->first_name ?: $recipient->name,
            'title' => $badge === 'approved' ? 'Travel Order Approved' : 'Travel Order Rejected',
            'intro' => $badge === 'approved'
                ? 'Your travel order has been fully approved.'
                : 'Your travel order was rejected during the approval process.',
            'reference_number' => $order->travel_order_number,
            'transaction_type' => 'Travel Order',
            'description' => $description,
            'action_at' => ManilaTime::now()->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A'),
            'actor_name' => $approver->name,
            'status_label' => strtoupper($statusLabel),
            'status_badge' => $badge === 'approved' ? 'approved' : 'rejected',
            'cta_label' => 'VIEW TRAVEL ORDER',
            'cta_url' => route('employee.travel-orders.show', $order),
        ];

        if ($rejectionReason !== null) {
            $payload['rejection_reason'] = $rejectionReason;
        }

        return $payload;
    }

    private function leaveTransactionPayload(
        LeaveApplication $application,
        User $recipient,
        User $approver,
        string $statusLabel,
        string $badge,
        ?string $rejectionReason = null,
    ): array {
        $dates = $application->start_date->format('M j').'–'.$application->end_date->format('M j, Y');
        $description = $application->leaveTypeLabel().' · '.$dates.' · '.$application->requested_days.' day(s)';

        $payload = [
            'greeting_name' => $recipient->employee?->first_name ?: $recipient->name,
            'title' => $badge === 'approved' ? 'Transaction Approved' : 'Transaction Rejected',
            'intro' => $badge === 'approved'
                ? 'Your leave application received an approval update.'
                : 'Your leave application was denied during the approval process.',
            'reference_number' => $application->application_number,
            'transaction_type' => 'Leave Application',
            'description' => $description,
            'action_at' => ManilaTime::now()->timezone(ManilaTime::TIMEZONE)->format('F j, Y g:i A'),
            'actor_name' => $approver->name,
            'status_label' => strtoupper($statusLabel),
            'status_badge' => $badge === 'approved' ? $this->leaveApprovalBadge($statusLabel) : 'rejected',
            'cta_label' => 'VIEW TRANSACTION',
            'cta_url' => route('employee.leave.show', $application),
        ];

        if ($rejectionReason !== null) {
            $payload['rejection_reason'] = $rejectionReason;
        }

        return $payload;
    }

    private function leaveApprovalBadge(string $statusLabel): string
    {
        if (stripos($statusLabel, 'pending') !== false || stripos($statusLabel, 'partial') !== false) {
            return 'pending';
        }

        if (stripos($statusLabel, 'approved') !== false) {
            return 'approved';
        }

        return 'pending';
    }

    private function deliver(
        EmailNotificationType $type,
        string $recipient,
        string $subject,
        \Illuminate\Mail\Mailable $mailable,
        string $dedupeKey,
        ?int $userId = null,
        ?object $notifiable = null,
        ?array $meta = null,
    ): void {
        if (! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (EmailNotificationLog::query()->where('dedupe_key', $dedupeKey)->where('delivery_status', 'sent')->exists()) {
            return;
        }

        $log = EmailNotificationLog::query()->firstOrCreate(
            ['dedupe_key' => $dedupeKey],
            [
                'notification_type' => $type,
                'recipient' => $recipient,
                'subject' => $subject,
                'delivery_status' => 'pending',
                'user_id' => $userId,
                'notifiable_type' => $notifiable ? $notifiable::class : null,
                'notifiable_id' => $notifiable->id ?? null,
                'meta' => $meta,
            ]
        );

        if ($log->delivery_status === 'sent') {
            return;
        }

        try {
            Mail::to($recipient)->send($mailable);

            $log->update([
                'delivery_status' => 'sent',
                'sent_at' => now(),
                'error_message' => null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('BACS email notification failed', [
                'type' => $type->value,
                'recipient' => $recipient,
                'dedupe_key' => $dedupeKey,
                'error' => $e->getMessage(),
            ]);

            $log->update([
                'delivery_status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }
}
