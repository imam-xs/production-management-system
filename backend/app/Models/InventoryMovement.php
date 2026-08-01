<?php

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row of the append-only stock ledger.
 *
 * @property int $id
 * @property int $item_id
 * @property int|null $batch_id
 * @property MovementType $type
 * @property string $quantity Signed: negative for consumption.
 * @property string $balance_after
 * @property string|null $note
 */
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'item_id',
        'batch_id',
        'type',
        'quantity',
        'balance_after',
        'reference_type',
        'reference_id',
        'note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        // withTrashed — see Batch::item(). A ledger entry must never lose the
        // name of what it moved.
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    /**
     * What caused this movement — typically a ProductionOrder.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
