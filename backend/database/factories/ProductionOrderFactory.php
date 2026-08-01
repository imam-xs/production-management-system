<?php

namespace Database\Factories;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Models\Item;
use App\Models\ProductionOrder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionOrder>
 */
class ProductionOrderFactory extends Factory
{
    protected $model = ProductionOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_number' => strtoupper($this->faker->unique()->bothify('PO-########-####')),
            'stage' => ProductionStage::RawToSemiFinished,
            'output_item_id' => Item::factory()->semiFinished(),
            'planned_quantity' => $this->faker->randomFloat(4, 10, 100),
            'produced_quantity' => 0,
            'status' => ProductionOrderStatus::Pending,
            'completed_at' => null,
            'created_by' => null,
        ];
    }

    public function stage(ProductionStage $stage): self
    {
        return $this->state([
            'stage' => $stage,
            'output_item_id' => Item::factory()->ofType($stage->outputType()),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProductionOrderStatus::Completed,
            'produced_quantity' => $attributes['planned_quantity'],
            'completed_at' => now(),
        ]);
    }

    public function failed(string $reason = 'Insufficient input inventory.'): self
    {
        return $this->state([
            'status' => ProductionOrderStatus::Failed,
            'failure_reason' => $reason,
        ]);
    }
}
