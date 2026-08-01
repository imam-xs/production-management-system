<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\ProductionConsumption;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionConsumption>
 */
class ProductionConsumptionFactory extends Factory
{
    protected $model = ProductionConsumption::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrder::factory(),
            'input_batch_id' => Batch::factory(),
            'quantity_consumed' => $this->faker->randomFloat(4, 1, 50),
        ];
    }

    public function edge(ProductionOrder $order, Batch $inputBatch, float $quantity): self
    {
        return $this->state([
            'production_order_id' => $order->id,
            'input_batch_id' => $inputBatch->id,
            'quantity_consumed' => $quantity,
        ]);
    }
}
