<?php

namespace Database\Factories;

use App\Enums\BatchOrigin;
use App\Models\Batch;
use App\Models\Item;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Batch>
 */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(4, 10, 1000);

        return [
            'batch_number' => strtoupper($this->faker->unique()->bothify('RM-########-####')),
            'item_id' => Item::factory()->rawMaterial(),
            'quantity_produced' => $quantity,
            'quantity_remaining' => $quantity,
            'origin' => BatchOrigin::Purchase,
            'production_order_id' => null,
            'produced_at' => $this->faker->dateTimeBetween('-30 days'),
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

    public function forItem(Item $item): self
    {
        return $this->state(['item_id' => $item->id]);
    }

    /**
     * A manufactured batch, linked to the run that created it.
     */
    public function produced(ProductionOrder $order): self
    {
        return $this->state([
            'origin' => BatchOrigin::Production,
            'production_order_id' => $order->id,
            'item_id' => $order->output_item_id,
        ]);
    }
}
