<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

class PasswordResetLinkService
{
    public function signedResetUrl(User $user, int $minutesValid = 60): string
    {
        return URL::temporarySignedRoute(
            'password.reset.show',
            now()->addMinutes($minutesValid),
            ['user' => $user->id]
        );
    }

    public function userEligibleForReset(User $user): bool
    {
        if (! $user->isActive()) {
            return false;
        }

        $email = $user->email;

        return filled($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}
