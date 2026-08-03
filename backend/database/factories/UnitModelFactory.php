<?php

namespace Database\Factories;

use App\Models\UnitModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UnitModel>
 */
class UnitModelFactory extends Factory
{
    protected $model = UnitModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtolower($this->faker->unique()->lexify('??')),
            'name' => $this->faker->word(),
        ];
    }
}
