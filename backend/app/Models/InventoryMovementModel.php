<?php

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\InventoryMovementModelFactory;
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
class InventoryMovementModel extends Model
{
    /** @use HasFactory<InventoryMovementModelFactory> */
    use HasFactory;

    // the class name no longer matches the table, so name it explicitly
    protected $table = 'inventory_movements';

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
     * @return BelongsTo<ItemModel, $this>
     */
    public function item(): BelongsTo
    {
        // withTrashed — see BatchModel::item(). A ledger entry must never lose the
        // name of what it moved.
        return $this->belongsTo(ItemModel::class)->withTrashed();
    }

    /**
     * @return BelongsTo<BatchModel, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchModel::class);
    }

    /**
     * What caused this movement — typically a ProductionOrderModel.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
