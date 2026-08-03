<?php

namespace App\Models;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use Carbon\CarbonInterface;
use Database\Factories\ProductionOrderModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * The casts below are what actually convert these columns; the annotations let
 * static analysis see the enum and date types rather than the raw strings.
 *
 * @property ProductionStage $stage
 * @property ProductionOrderStatus $status
 * @property CarbonInterface|null $completed_at
 */
class ProductionOrderModel extends Model
{
    /** @use HasFactory<ProductionOrderModelFactory> */
    use HasFactory;

    // the class name no longer matches the table, so name it explicitly
    protected $table = 'production_orders';

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
     * @return BelongsTo<ItemModel, $this>
     */
    public function outputItem(): BelongsTo
    {
        return $this->belongsTo(ItemModel::class, 'output_item_id')->withTrashed();
    }

    /**
     * @return BelongsTo<UserModel, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'created_by');
    }

    /**
     * @return HasOne<BatchModel, $this>
     */
    public function outputBatch(): HasOne
    {
        return $this->hasOne(BatchModel::class, 'production_order_id');
    }

    /**
     * Which input batches this run drew from, and how much of each.
     *
     * @return HasMany<ProductionConsumptionModel, $this>
     */
    public function consumptions(): HasMany
    {
        return $this->hasMany(ProductionConsumptionModel::class, 'production_order_id');
    }

    /**
     * @return HasMany<ProductionEventLogModel, $this>
     */
    public function eventLogs(): HasMany
    {
        return $this->hasMany(ProductionEventLogModel::class, 'production_order_id');
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
