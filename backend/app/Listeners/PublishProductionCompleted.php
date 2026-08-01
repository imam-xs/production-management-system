<?php

namespace App\Listeners;

use App\Events\ProductionCompleted;
use App\Messaging\MessagePublisherInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turns the domain event into a message on the bus.
 *
 * Note the try/catch. By the time this runs the transaction has already
 * committed — the stock has moved and the order is complete. If RabbitMQ is
 * unreachable we must not turn that successful production run into an HTTP 500,
 * so the failure is logged and swallowed.
 *
 * The honest trade-off: that makes publishing at-most-once. A dropped message
 * means the event log misses a row, though inventory and traceability (the
 * things that actually matter) stay correct because they were written
 * synchronously. Closing the gap properly needs a transactional outbox — write
 * the message to a DB table inside the same transaction, then have a separate
 * process drain it — which is deliberately out of scope here and noted as such.
 */
class PublishProductionCompleted
{
    public function __construct(
        private readonly MessagePublisherInterface $publisher,
    ) {}

    public function handle(ProductionCompleted $event): void
    {
        $order = $event->order;
        $order->loadMissing(['outputItem', 'outputBatch', 'consumptions.inputBatch.item']);

        $payload = [
            'production_order_id' => $order->id,
            'order_number' => $order->order_number,
            'stage' => $order->stage->value,
            'output' => [
                'item_sku' => $order->outputItem->sku,
                'item_name' => $order->outputItem->name,
                'item_type' => $order->outputItem->type->value,
                'batch_number' => $order->outputBatch?->batch_number,
                'quantity' => (string) $order->produced_quantity,
            ],
            'consumed' => $order->consumptions->map(fn ($c): array => [
                'batch_number' => $c->inputBatch->batch_number,
                'item_sku' => $c->inputBatch->item->sku,
                'quantity' => (string) $c->quantity_consumed,
            ])->all(),
            'completed_at' => $order->completed_at?->toIso8601String(),
        ];

        try {
            $eventId = $this->publisher->publish(
                $order->stage->routingKey(),
                'production.completed',
                $payload,
            );

            Log::info('[message-bus] published production.completed', [
                'event_id' => $eventId,
                'order_number' => $order->order_number,
            ]);
        } catch (Throwable $e) {
            Log::error('[message-bus] publish failed — production itself was committed', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
