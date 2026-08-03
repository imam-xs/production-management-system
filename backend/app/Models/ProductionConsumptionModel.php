<?php

namespace App\Models;

use Database\Factories\ProductionConsumptionModelFactory;
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
class ProductionConsumptionModel extends Model
{
    /** @use HasFactory<ProductionConsumptionModelFactory> */
    use HasFactory;

    // the class name no longer matches the table, so name it explicitly
    protected $table = 'production_consumptions';

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
     * @return BelongsTo<ProductionOrderModel, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderModel::class);
    }

    /**
     * @return BelongsTo<BatchModel, $this>
     */
    public function inputBatch(): BelongsTo
    {
        return $this->belongsTo(BatchModel::class, 'input_batch_id');
    }
}
