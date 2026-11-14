<?php

namespace Tests\Feature;

use App\Enums\AccountStatus;
use App\Enums\EmailNotificationType;
use App\Enums\EmploymentStatus;
use App\Enums\UserRole;
use App\Mail\PasswordResetMail;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\PasswordResetLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

  /** @return array{user: User, employee: Employee} */
    private function activeEmployee(): array
    {
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
            'email' => 'reset.me@bacs.test',
            'username' => 'reset.user',
            'password' => 'OldPass1!',
            'role' => UserRole::Employee,
            'status' => AccountStatus::Active,
            'must_change_password' => false,
        ]);

        $employee = Employee::query()->create([
            'user_id' => $user->id,
            'employee_number' => 'EMP-RESET-01',
            'first_name' => 'Reset',
            'last_name' => 'User',
            'email' => $user->email,
            'employment_status' => EmploymentStatus::Regular,
            'work_schedule_id' => $schedule->id,
        ]);

        return ['user' => $user, 'employee' => $employee];
    }

    public function test_forgot_password_page_is_reachable(): void
    {
        $this->get(route('password.request'))->assertOk();
    }

    public function test_reset_request_sends_email_for_valid_login(): void
    {
        Mail::fake();

        ['user' => $user] = $this->activeEmployee();

        $response = $this->post(route('password.email'), ['login' => $user->username]);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('reset_status', 'sent');
        $response->assertSessionHas('reset_message');

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) use ($user): bool {
            return $mail->payload['username'] === $user->username
                && filled($mail->payload['reset_url'] ?? null);
        });

        $this->assertDatabaseHas('email_notification_logs', [
            'notification_type' => EmailNotificationType::PasswordReset->value,
            'recipient' => $user->email,
            'delivery_status' => 'sent',
        ]);
    }

    public function test_reset_request_reports_when_account_is_not_found(): void
    {
        Mail::fake();

        $response = $this->post(route('password.email'), ['login' => 'nobody-here']);

        $response->assertRedirect(route('password.request'));
        $response->assertSessionHas('reset_status', 'not_found');
        $response->assertSessionHas('reset_message');
        Mail::assertNothingSent();
    }

    public function test_user_can_reset_password_via_signed_link(): void
    {
        ['user' => $user] = $this->activeEmployee();

        $resetUrl = app(PasswordResetLinkService::class)->signedResetUrl($user);

        $this->get($resetUrl)->assertOk();

        $postUrl = route('password.reset.store', ['user' => $user->id]).'?'.parse_url($resetUrl, PHP_URL_QUERY);

        $this->post($postUrl, [
            'password' => 'NewPass2!',
            'password_confirmation' => 'NewPass2!',
        ])->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('NewPass2!', $user->password));
    }

    public function test_expired_reset_link_is_rejected(): void
    {
        ['user' => $user] = $this->activeEmployee();

        URL::useOrigin('http://localhost');
        $resetUrl = URL::temporarySignedRoute(
            'password.reset.show',
            now()->subMinute(),
            ['user' => $user->id]
        );

        $this->get($resetUrl)->assertForbidden();
    }
}
