<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmailNotificationType;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Mail\AccountCreatedMail;
use App\Mail\TransactionApprovedMail;
use App\Mail\TransactionRejectedMail;
use App\Models\EmailNotificationLog;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_created_email_is_logged_and_not_duplicated(): void
    {
        Mail::fake();

        $schedule = WorkSchedule::query()->create([
            'name' => 'Regular',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'grace_period_minutes' => 10,
            'break_start' => '12:00:00',
            'break_end' => '13:00:00',
            'required_minutes' => 480,
            'work_days' => [1, 2, 3, 4, 5],
            'is_default' => true,
            'status' => AccountStatus::Active,
        ]);

        $user = User::factory()->create([
            'email' => 'newhire@bacs.test',
            'role' => UserRole::Employee,
            'status' => AccountStatus::Active,
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-9001',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
        ]);

        $service = app(EmailNotificationService::class);
        $service->accountCreated($user, $employee);
        $service->accountCreated($user, $employee);

        Mail::assertSent(AccountCreatedMail::class, 1);

        $this->assertDatabaseHas('email_notification_logs', [
            'notification_type' => EmailNotificationType::AccountCreated->value,
            'recipient' => 'newhire@bacs.test',
            'delivery_status' => 'sent',
        ]);

        $this->assertSame(1, EmailNotificationLog::query()->count());
    }

    public function test_preview_renders_html_for_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->get(route('admin.email-notifications.preview', ['template' => 'transaction_approved', 'raw' => 1]))
            ->assertOk()
            ->assertSee('BACS Construction and Development Corporation', false)
            ->assertSee('VIEW TRANSACTION', false);
    }

    public function test_sample_mailables_use_expected_subjects(): void
    {
        $service = app(EmailNotificationService::class);

        $approved = new TransactionApprovedMail($service->sampleTransactionApprovedPayload());
        $rejected = new TransactionRejectedMail($service->sampleTransactionRejectedPayload());
        $account = new AccountCreatedMail($service->sampleAccountPayload());

        $this->assertSame('BACS | Transaction Approved - LV-2026-00042', $approved->envelope()->subject);
        $this->assertSame('BACS | Transaction Rejected - DTR-CR-00018', $rejected->envelope()->subject);
        $this->assertSame('BACS | Account Successfully Created', $account->envelope()->subject);
    }
}
