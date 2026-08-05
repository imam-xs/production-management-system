<?php

namespace App\Models;

use Database\Factories\ProductionConsumptionModelFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $quantity_consumed */
class ProductionConsumptionModel extends Model
{
    /** @use HasFactory<ProductionConsumptionModelFactory> */
    use HasFactory;

    protected $table = 'production_consumptions';

    protected $fillable = [
        'production_order_id',
        'input_batch_id',
        'quantity_consumed',
    ];

    protected function casts(): array
    {
        return [
            'quantity_consumed' => 'decimal:4',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderModel::class);
    }

    public function inputBatch(): BelongsTo
    {
        return $this->belongsTo(BatchModel::class, 'input_batch_id');
    }
}
