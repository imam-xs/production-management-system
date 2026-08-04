<?php

namespace Database\Factories;

use App\Models\BatchModel;
use App\Models\ProductionConsumptionModel;
use App\Models\ProductionOrderModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionConsumptionModel>
 */
class ProductionConsumptionModelFactory extends Factory
{
    protected $model = ProductionConsumptionModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_order_id' => ProductionOrderModel::factory(),
            'input_batch_id'      => BatchModel::factory(),
            'quantity_consumed'   => $this->faker->randomFloat(4, 1, 50),
        ];
    }

    public function edge(ProductionOrderModel $order, BatchModel $inputBatch, float $quantity): self
    {
        return $this->state([
            'production_order_id' => $order->id,
            'input_batch_id'      => $inputBatch->id,
            'quantity_consumed'   => $quantity,
        ]);
    }
}
