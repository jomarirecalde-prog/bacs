<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class AccountPasswordSetupService
{
    public function signedSetupUrl(User $user, int $hoursValid = 72): string
    {
        return URL::temporarySignedRoute(
            'password.setup.show',
            now()->addHours($hoursValid),
            ['user' => $user->id]
        );
    }

    public function userEligibleForSetup(User $user): bool
    {
        return $user->isActive() && $user->must_change_password;
    }
}
