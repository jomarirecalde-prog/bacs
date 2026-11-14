<?php

namespace App\Services;

use App\Models\User;

class UserLoginLookupService
{
    public function findByLogin(string $login): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        if (filter_var($login, FILTER_VALIDATE_EMAIL)) {
            return User::query()->where('email', $login)->first();
        }

        $byUsername = User::query()->where('username', $login)->first();
        if ($byUsername) {
            return $byUsername;
        }

        return User::query()
            ->whereHas('employee', fn ($query) => $query->where('employee_number', $login))
            ->first();
    }
}
