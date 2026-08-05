<?php

namespace App\Models;

use App\Enums\BatchOrigin;
use Carbon\CarbonInterface;
use Database\Factories\BatchModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $quantity_produced
 * @property string $quantity_remaining
 * @property BatchOrigin $origin
 * @property CarbonInterface $produced_at
 */
class BatchModel extends Model
{
    /** @use HasFactory<BatchModelFactory> */
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

    protected function casts(): array
    {
        return [
            'origin' => BatchOrigin::class,
            'quantity_produced' => 'decimal:4',
            'quantity_remaining' => 'decimal:4',
            'produced_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class)->withTrashed();
    }

    // null for purchased material, where the upstream trace ends
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderModel::class);
    }

    // the downstream direction: where did this batch go?
    public function consumedBy(): HasMany
    {
        return $this->hasMany(ProductionConsumptionModel::class, 'input_batch_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovementModel::class, 'batch_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('quantity_remaining', '>', 0);
    }

    public function scopeFifo(Builder $query): Builder
    {
        return $query->orderBy('produced_at')->orderBy('id');
    }
}
