<?php

namespace Database\Factories;

use App\Models\UnitModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class UnitModelFactory extends Factory
{
    protected $model = UnitModel::class;

    public function definition(): array
    {
        return [
            'code' => strtolower($this->faker->unique()->lexify('??')),
            'name' => $this->faker->word(),
        ];
    }
}
