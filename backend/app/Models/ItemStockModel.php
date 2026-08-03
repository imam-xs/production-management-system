<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached on-hand quantity for an item.
 *
 * Written only by InventoryService, always inside the transaction that wrote the
 * corresponding inventory_movements rows. Never the authority — see the
 * item_stocks migration for why it exists.
 *
 * @property int $item_id
 * @property string $quantity_on_hand
 */
class ItemStockModel extends Model
{
    protected $table = 'item_stocks';

    protected $primaryKey = 'item_id';

    public $incrementing = false;

    /**
     * Only `updated_at` exists on this table — there is no creation event worth
     * recording for a cache row.
     */
    public const CREATED_AT = null;

    protected $fillable = [
        'item_id',
        'quantity_on_hand',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_id' => 'integer',
            'quantity_on_hand' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<ItemModel, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class);
    }
}
