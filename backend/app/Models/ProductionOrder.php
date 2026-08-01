<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use Carbon\CarbonInterface;
use Database\Factories\ProductionOrderFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A production run — the anchor for both inventory movement and traceability.
 *
 * @property int $id
 * @property string $order_number
 * @property ProductionStage $stage
 * @property int $output_item_id
 * @property string $planned_quantity
 * @property string $produced_quantity
 * @property ProductionOrderStatus $status
 * @property CarbonInterface|null $completed_at
 * @property string|null $failure_reason
 * @property int|null $created_by
 */
class ProductionOrder extends Model
{
    /** @use HasFactory<ProductionOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'stage',
        'output_item_id',
        'planned_quantity',
        'produced_quantity',
        'status',
        'completed_at',
        'failure_reason',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => ProductionStage::class,
            'status' => ProductionOrderStatus::class,
            'planned_quantity' => 'decimal:4',
            'produced_quantity' => 'decimal:4',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Item, $this>
     */
    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'output_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The batch this run produced. One order yields exactly one output batch,
     * created in the same transaction that completes the order.
     *
     * @return HasOne<Batch, $this>
     */
    public function outputBatch(): HasOne
    {
        return $this->hasOne(Batch::class, 'production_order_id');
    }

    /**
     * Which input batches this run drew from, and how much of each.
     *
     * @return HasMany<ProductionConsumption, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumption::class);
    }

    /**
     * @return HasMany<ProductionEventLog, $this>
     */
    public function eventLogs(): HasMany
    {
        return $this->hasMany(ProductionEventLog::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', ProductionOrderStatus::Completed);
    }
}
