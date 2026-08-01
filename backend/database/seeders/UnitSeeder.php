<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['code' => 'kg', 'name' => 'Kilogram'],
            ['code' => 'ton', 'name' => 'Metric Ton'],
            ['code' => 'pcs', 'name' => 'Pieces'],
            ['code' => 'm', 'name' => 'Metre'],
            ['code' => 'sheet', 'name' => 'Sheet'],
        ];

        foreach ($units as $unit) {
            Unit::query()->updateOrCreate(['code' => $unit['code']], $unit);
        }
    }
}
