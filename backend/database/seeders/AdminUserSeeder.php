<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

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
