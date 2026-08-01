<?php

namespace Database\Factories;

use App\Models\BillOfMaterial;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillOfMaterial>
 */
class BillOfMaterialFactory extends Factory
{
    protected $model = BillOfMaterial::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'output_item_id' => Item::factory()->semiFinished(),
            'input_item_id' => Item::factory()->rawMaterial(),
            'quantity_per_unit' => $this->faker->randomFloat(4, 0.5, 5),
        ];
    }

    public function recipe(Item $output, Item $input, float $quantityPerUnit): self
    {
        return $this->state([
            'output_item_id' => $output->id,
            'input_item_id' => $input->id,
            'quantity_per_unit' => $quantityPerUnit,
        ]);
    }
}
