<?php

namespace Database\Factories;

use App\Models\BillOfMaterialModel;
use App\Models\ItemModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillOfMaterialModel>
 */
class BillOfMaterialModelFactory extends Factory
{
    protected $model = BillOfMaterialModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'output_item_id'    => ItemModel::factory()->semiFinished(),
            'input_item_id'     => ItemModel::factory()->rawMaterial(),
            'quantity_per_unit' => $this->faker->randomFloat(4, 0.5, 5),
        ];
    }

    public function recipe(ItemModel $output, ItemModel $input, float $quantityPerUnit): self
    {
        return $this->state([
            'output_item_id'    => $output->id,
            'input_item_id'     => $input->id,
            'quantity_per_unit' => $quantityPerUnit,
        ]);
    }
}
