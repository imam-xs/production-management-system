<?php

namespace Database\Factories;

use App\Enums\BatchOrigin;
use App\Models\BatchModel;
use App\Models\ItemModel;
use App\Models\ProductionOrderModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BatchModel>
 */
class BatchModelFactory extends Factory
{
    protected $model = BatchModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(4, 10, 1000);

        return [
            'batch_number'        => strtoupper($this->faker->unique()->bothify('RM-########-####')),
            'item_id'             => ItemModel::factory()->rawMaterial(),
            'quantity_produced'   => $quantity,
            'quantity_remaining'  => $quantity,
            'origin'              => BatchOrigin::Purchase,
            'production_order_id' => null,
            'produced_at'         => $this->faker->dateTimeBetween('-30 days'),
        ];
    }

    public function withQuantity(float $quantity): self
    {
        return $this->state([
            'quantity_produced' => $quantity,
            'quantity_remaining' => $quantity,
        ]);
    }

    public function depleted(): self
    {
        return $this->state(['quantity_remaining' => 0]);
    }

    public function forItem(ItemModel $item): self
    {
        return $this->state(['item_id' => $item->id]);
    }

    // a manufactured batch, linked to the run that created it
    public function produced(ProductionOrderModel $order): self
    {
        return $this->state([
            'origin'              => BatchOrigin::Production,
            'production_order_id' => $order->id,
            'item_id'             => $order->output_item_id,
        ]);
    }
}
