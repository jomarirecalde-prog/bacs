<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SessionManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_sessions_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('sessions'));
    }

    public function test_guest_cannot_access_session_heartbeat(): void
    {
        $this->getJson(route('session.heartbeat'))
            ->assertUnauthorized();
    }

    public function test_authenticated_user_can_heartbeat_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('session.heartbeat'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertJsonStructure([
                'csrf_token',
                'expires_at',
                'lifetime_minutes',
                'warn_before_minutes',
            ])
            ->assertHeader('X-CSRF-TOKEN');
    }

    public function test_authenticated_user_can_extend_session(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('session.extend'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('authenticated', true)
            ->assertHeader('X-CSRF-TOKEN');
    }

    public function test_csrf_mismatch_returns_json_session_expired_response(): void
    {
        $request = Request::create(route('session.extend'), 'POST', server: [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(419, $response->getStatusCode());
        $this->assertSame('SESSION_EXPIRED', $response->getData(true)['code']);
        $this->assertSame(
            'Your BACS session has expired. Please sign in again.',
            $response->getData(true)['message'],
        );
    }

    public function test_csrf_mismatch_returns_session_expired_page_for_html_requests(): void
    {
        $request = Request::create('/admin/dashboard', 'POST');

        $response = $this->app->make(\Illuminate\Contracts\Debug\ExceptionHandler::class)
            ->render($request, new TokenMismatchException('CSRF token mismatch.'));

        $this->assertSame(419, $response->getStatusCode());
        $this->assertStringContainsString('Your BACS session has expired', $response->getContent());
    }

    public function test_password_change_must_change_user_can_still_heartbeat(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
        ]);

        $this->actingAs($user)
            ->getJson(route('session.heartbeat'))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
