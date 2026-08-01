<?php

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Batch;
use App\Models\InventoryMovement;
use App\Models\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(4, 1, 500);

        return [
            'item_id' => Item::factory()->rawMaterial(),
            'batch_id' => Batch::factory(),
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
