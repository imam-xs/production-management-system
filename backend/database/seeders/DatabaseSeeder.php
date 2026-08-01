<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Order matters: units before items, items before recipes, and the demo
 * production chain last because it executes real production runs through the
 * domain services and therefore needs a stocked catalogue to work with.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            UnitSeeder::class,
            ItemSeeder::class,
            BillOfMaterialSeeder::class,
            DemoProductionSeeder::class,
        ]);
    }
}
