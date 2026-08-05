<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// record of a production event handled by the RabbitMQ consumer. rows here
// are written by the worker process, never by the API request
/**
 * @property array<string, mixed> $payload
 * @property CarbonInterface $occurred_at
 * @property CarbonInterface $processed_at
 */
class ProductionEventLogModel extends Model
{
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

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderModel::class);
    }
}
