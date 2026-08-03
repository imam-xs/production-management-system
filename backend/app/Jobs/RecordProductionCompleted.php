<?php

namespace App\Jobs;

use App\Models\ProductionEventLogModel;
use App\Models\ProductionOrderModel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class RecordProductionCompleted implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $eventId,
        public readonly string $routingKey,
        public readonly array $payload,
        public readonly string $occurredAt,
    ) {}

    public static function for(ProductionOrderModel $order): self
    {
        $order->loadMissing(['outputItem', 'outputBatch', 'consumptions.inputBatch.item']);

        return new self(
            eventId: (string) Str::uuid(),
            routingKey: $order->stage->routingKey(),
            payload: [
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
            ],
            occurredAt: now()->toIso8601String(),
        );
    }

    public function handle(): void
    {
        try {
            ProductionEventLogModel::query()->create([
                'event_id' => $this->eventId,
                'event_type' => 'production.completed',
                'routing_key' => $this->routingKey,
                'production_order_id' => $this->payload['production_order_id'] ?? null,
                'payload' => $this->payload,
                'attempts' => $this->attempts(),
                'occurred_at' => $this->occurredAt,
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::info('[consumer] duplicate event ignored', ['event_id' => $this->eventId]);

            return;
        }

        Log::info('[consumer] production event recorded', [
            'event_id' => $this->eventId,
            'order_number' => $this->payload['order_number'] ?? null,
            'stage' => $this->payload['stage'] ?? null,
            'output_batch' => $this->payload['output']['batch_number'] ?? null,
            'inputs_consumed' => count($this->payload['consumed'] ?? []),
        ]);

        $this->sendNotification();
    }

    // stands in for a real notification channel (mail, Slack)
    private function sendNotification(): void
    {
        $output = $this->payload['output'] ?? [];

        Log::info(sprintf(
            '[notification] BatchModel %s of %s (%s %s) is ready.',
            $output['batch_number'] ?? '?',
            $output['item_name'] ?? '?',
            $output['quantity'] ?? '?',
            $output['item_type'] ?? '',
        ));
    }
}
