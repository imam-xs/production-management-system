<?php

namespace App\Models;

use App\Enums\BatchOrigin;
use Carbon\CarbonInterface;
use Database\Factories\BatchFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A uniquely identifiable lot of one item.
 *
 * @property int $id
 * @property string $batch_number
 * @property int $item_id
 * @property string $quantity_produced
 * @property string $quantity_remaining
 * @property BatchOrigin $origin
 * @property int|null $production_order_id
 * @property CarbonInterface $produced_at
 */
class Batch extends Model
{
    /** @use HasFactory<BatchFactory> */
    use HasFactory;

    protected $table = 'batches';

    protected $fillable = [
        'batch_number',
        'item_id',
        'quantity_produced',
        'quantity_remaining',
        'origin',
        'production_order_id',
        'produced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'origin' => BatchOrigin::class,
            'quantity_produced' => 'decimal:4',
            'quantity_remaining' => 'decimal:4',
            'produced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function item(): BelongsTo
    {
        // withTrashed: a batch is history, and history must keep resolving even
        // if the item was later retired. Without this the relation returns null
        // for a soft-deleted item and every trace through this batch breaks.
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /**
     * The run that created this batch — null for purchased material, which is
     * where the upstream trace terminates.
     *
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    /**
     * Runs that consumed this batch — the downstream ("where did it go?")
     * direction of traceability.
     *
     * @return HasMany<ProductionConsumption, $this>
     */
    public function consumedBy(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class, 'input_batch_id');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * Batches with stock left, for FIFO allocation.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity_remaining', '>', 0);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderBy('produced_at')->orderBy('id');
    }
}
