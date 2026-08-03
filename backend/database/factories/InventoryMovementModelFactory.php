<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\BatchModel;
use App\Models\InventoryMovementModel;
use App\Models\ItemModel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovementModel>
 */
class InventoryMovementModelFactory extends Factory
{
    protected $model = InventoryMovementModel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(4, 1, 500);

        return [
            'item_id' => ItemModel::factory()->rawMaterial(),
            'batch_id' => BatchModel::factory(),
            'type' => MovementType::Receipt,
            'quantity' => $quantity,
            'balance_after' => $quantity,
            'note' => null,
        ];
    }

    public function ofType(MovementType $type): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
            'quantity' => abs((float) $attributes['quantity']) * ($type->sign() < 0 ? -1 : 1),
        ]);
    }
}
