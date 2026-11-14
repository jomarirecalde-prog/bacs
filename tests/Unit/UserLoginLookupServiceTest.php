<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\UserLoginLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserLoginLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): UserLoginLookupService
    {
        return app(UserLoginLookupService::class);
    }

    public function test_find_by_username_is_case_insensitive(): void
    {
        $user = User::factory()->create(['username' => 'juan.delacruz', 'email' => 'juan@bacs.test']);

        $found = $this->service()->findByLogin('JUAN.DELACRUZ');

        $this->assertNotNull($found);
        $this->assertTrue($found->is($user));
    }

    public function test_email_shape_falls_back_to_username_when_email_not_found(): void
    {
        $user = User::factory()->create([
            'username' => 'legacy.user',
            'email' => 'other@bacs.test',
        ]);

        $found = $this->service()->findByLogin('legacy.user');

        $this->assertNotNull($found);
        $this->assertTrue($found->is($user));
    }
}
