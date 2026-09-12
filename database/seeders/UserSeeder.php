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
            if (app()->environment('testing')) {
                $admin->password = 'password';
                $admin->must_change_password = false;
            } else {
                $password = env('ADMIN_INITIAL_PASSWORD');

                if (! filled($password)) {
                    $password = bin2hex(random_bytes(12));
                    $admin->must_change_password = true;
                    $this->command?->warn('Super Admin created. Username: admin');
                    $this->command?->warn("Temporary password: {$password}");
                    $this->command?->warn('Set ADMIN_INITIAL_PASSWORD in .env to choose a fixed dev password.');
                } else {
                    $admin->must_change_password = filter_var(
                        env('ADMIN_FORCE_PASSWORD_CHANGE', false),
                        FILTER_VALIDATE_BOOL
                    );
                }

                $admin->password = $password;
            }
        }

        $admin->save();
    }
}
