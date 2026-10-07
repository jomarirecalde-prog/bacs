<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BacsPasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_weak_password_is_rejected_on_profile_change(): void
    {
        $user = User::factory()->create(['password' => 'OldPass1!']);

        $this->actingAs($user)
            ->putJson(route('profile.password.update'), [
                'current_password' => 'OldPass1!',
                'password' => 'weakpass',
                'password_confirmation' => 'weakpass',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_strong_password_is_accepted_on_profile_change(): void
    {
        $user = User::factory()->create(['password' => 'OldPass1!']);

        $this->actingAs($user)
            ->putJson(route('profile.password.update'), [
                'current_password' => 'OldPass1!',
                'password' => 'NewSecure1',
                'password_confirmation' => 'NewSecure1',
            ])
            ->assertOk();
    }
}
