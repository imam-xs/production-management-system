<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Record of a production event handled by the RabbitMQ consumer.
 *
 * Rows here are written by the worker process, never by the API request, so
 * their presence is evidence the asynchronous path actually ran.
 *
 * @property int $id
 * @property string $event_id
 * @property string $event_type
 * @property string $routing_key
 * @property int|null $production_order_id
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property CarbonInterface $occurred_at
 * @property CarbonInterface $processed_at
 */
class ProductionEventLogModel extends Model
{
    // the class name no longer matches the table, so name it explicitly
    protected $table = 'production_event_logs';

    protected $fillable = [
        'event_id',
        'event_type',
        'routing_key',
        'production_order_id',
        'payload',
        'attempts',
        'occurred_at',
        'processed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductionOrderModel, $this>
     */
    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderModel::class);
    }
}
