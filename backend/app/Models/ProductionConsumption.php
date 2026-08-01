<?php

namespace App\Models;

use Database\Factories\ProductionConsumptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One traceability edge: order X consumed quantity Q of batch B.
 *
 * @property int $id
 * @property int $production_order_id
 * @property int $input_batch_id
 * @property string $quantity_consumed
 */
class ProductionConsumption extends Model
{
    /** @use HasFactory<ProductionConsumptionFactory> */
    use HasFactory;

    protected $fillable = [
        'production_order_id',
        'input_batch_id',
        'quantity_consumed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_consumed' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<ProductionOrder, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    /**
     * @return BelongsTo<Batch, $this>
     */
    public function inputBatch(): BelongsTo
    {
        return $this->belongsTo(Batch::class, 'input_batch_id');
    }
}
