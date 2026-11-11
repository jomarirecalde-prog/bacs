<?php

namespace Tests\Feature;

use App\Enums\DataPrivacyConsentAction;
use App\Models\DataPrivacyConsent;
use App\Models\User;
use App\Support\PrivacyNotice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DataPrivacyConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_login_redirects_to_privacy_notice_when_consent_missing(): void
    {
        User::factory()->create([
            'username' => 'privacyuser',
            'email' => 'privacy@bacs.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'login' => 'privacyuser',
            'password' => 'password',
        ])
            ->assertRedirect(route('privacy.consent.show'));

        $this->get(route('employee.dashboard'))
            ->assertRedirect(route('privacy.consent.show'));
    }

    public function test_decline_logs_out_and_records_decline(): void
    {
        $user = User::factory()->create([
            'username' => 'declineuser',
            'password' => 'password',
        ]);

        $this->actingAs($user);

        DataPrivacyConsent::query()->where('user_id', $user->id)->delete();

        $this->post(route('privacy.consent.store'), ['action' => 'declined'])
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $this->assertDatabaseHas('data_privacy_consents', [
            'user_id' => $user->id,
            'notice_version' => PrivacyNotice::version(),
            'action' => DataPrivacyConsentAction::Declined->value,
        ]);
    }

    public function test_agreement_persists_version_timestamp_and_ip(): void
    {
        $user = User::factory()->create();
        DataPrivacyConsent::query()->where('user_id', $user->id)->delete();

        $this->actingAs($user);

        DataPrivacyConsent::query()->where('user_id', $user->id)->delete();

        $response = $this->postJson(route('privacy.consent.store'), [
            'action' => 'granted',
            'acknowledged' => '1',
        ]);

        $response->assertOk()->assertJsonPath('ok', true);

        $record = DataPrivacyConsent::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($record);
        $this->assertSame(PrivacyNotice::version(), $record->notice_version);
        $this->assertSame(DataPrivacyConsentAction::Granted, $record->action);
        $this->assertNotNull($record->consented_at);
        $this->assertNotNull($record->ip_address);
        $this->assertSame(PrivacyNotice::textHash(), $record->notice_text_hash);
    }

    public function test_relogin_with_current_version_skips_notice(): void
    {
        User::factory()->create([
            'username' => 'returning',
            'password' => 'password',
        ]);

        $this->post('/login', ['login' => 'returning', 'password' => 'password'])
            ->assertRedirect(route('privacy.consent.show'));

        $this->post(route('privacy.consent.store'), [
            'action' => 'granted',
            'acknowledged' => '1',
        ])->assertRedirect(route('home'));

        $this->post('/logout');

        $this->post('/login', ['login' => 'returning', 'password' => 'password'])
            ->assertRedirect(route('home'));
    }

    public function test_bumping_notice_version_re_triggers_notice(): void
    {
        $user = User::factory()->create([
            'username' => 'versioned',
            'password' => 'password',
        ]);

        DataPrivacyConsent::query()->create([
            'user_id' => $user->id,
            'notice_version' => '2025-01-01',
            'action' => DataPrivacyConsentAction::Granted,
            'consented_at' => now()->subDay(),
            'ip_address' => '127.0.0.1',
            'notice_text_hash' => PrivacyNotice::textHash(),
        ]);

        $this->post('/login', ['login' => 'versioned', 'password' => 'password'])
            ->assertRedirect(route('privacy.consent.show'));
    }

    public function test_consent_page_shows_notice_title(): void
    {
        $user = User::factory()->create();
        DataPrivacyConsent::query()->where('user_id', $user->id)->delete();

        $this->actingAs($user);
        DataPrivacyConsent::query()->where('user_id', $user->id)->delete();

        $this->get(route('privacy.consent.show'))
            ->assertOk()
            ->assertSee(PrivacyNotice::title(), false)
            ->assertSee('Agree and Continue', false);
    }
}
