<?php

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\ItemModel;
use App\Models\UnitModel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemModelFactory extends Factory
{
    protected $model = ItemModel::class;

    public function definition(): array
    {
        return [
            'sku' => strtoupper($this->faker->unique()->bothify('ITM-####-??')),
            'name' => ucwords($this->faker->words(2, true)),
            'type' => ItemType::Raw,
            'unit_id' => UnitModel::factory(),
            'description' => $this->faker->optional()->sentence(),
            'reorder_level' => $this->faker->randomFloat(4, 0, 100),
            'is_active' => true,
        ];
    }

    public function rawMaterial(): self
    {
        return $this->state(['type' => ItemType::Raw]);
    }

    public function semiFinished(): self
    {
        return $this->state(['type' => ItemType::SemiFinished]);
    }

    public function finished(): self
    {
        return $this->state(['type' => ItemType::Finished]);
    }

    public function ofType(ItemType $type): self
    {
        return $this->state(['type' => $type]);
    }

    public function inactive(): self
    {
        return $this->state(['is_active' => false]);
    }
}
