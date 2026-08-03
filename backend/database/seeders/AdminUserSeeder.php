<?php

namespace Database\Seeders;

use App\Models\UserModel;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        UserModel::query()->updateOrCreate(
            ['email' => 'admin@pms.test'],
            [
                'name' => 'Plant Administrator',
                'password' => 'password',
                'email_verified_at' => now(),
            ],
        );
    }
}
