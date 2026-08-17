<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrNew(['username' => 'admin']);
        $isNew = ! $admin->exists;

        $admin->fill([
            'name' => 'Super Admin',
            'email' => 'admin@bacs.test',
            'role' => UserRole::Admin,
            'status' => AccountStatus::Active,
            'must_change_password' => false,
        ]);

        if ($isNew) {
            $admin->password = 'password';
        }

        $admin->save();

        if ($isNew) {
            $this->command?->warn('Super Admin created. Username: admin');
        }
    }
}
