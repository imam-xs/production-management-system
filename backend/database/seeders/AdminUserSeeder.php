<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * A known operator account so a reviewer can obtain a token immediately.
 * Credentials are documented in the README.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@pms.test'],
            [
                'name' => 'Plant Administrator',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
