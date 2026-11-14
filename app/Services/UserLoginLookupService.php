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
            $user = $this->findByEmail($login);
            if ($user) {
                return $user;
            }
        }

        $user = $this->findByUsername($login);
        if ($user) {
            return $user;
        }

        return $this->findByEmployeeNumber($login);
    }

    public function findByEmail(string $email): ?User
    {
        $email = strtolower(trim($email));

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
    }

    public function findByUsername(string $username): ?User
    {
        $username = trim($username);

        return User::query()->whereRaw('LOWER(username) = ?', [strtolower($username)])->first();
    }

    public function findByEmployeeNumber(string $employeeNumber): ?User
    {
        $employeeNumber = trim($employeeNumber);

        return User::query()
            ->whereHas('employee', fn ($query) => $query->where('employee_number', $employeeNumber))
            ->first();
    }
}
