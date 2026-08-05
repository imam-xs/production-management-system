<?php

namespace App\Models;

use App\Enums\MovementType;
use Database\Factories\InventoryMovementModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property MovementType $type
 * @property string $quantity Signed: negative for consumption.
 * @property string $balance_after
 */
class InventoryMovementModel extends Model
{
    /** @use HasFactory<InventoryMovementModelFactory> */
    use HasFactory;

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

    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:4',
            'balance_after' => 'decimal:4',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class)->withTrashed();
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(BatchModel::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
