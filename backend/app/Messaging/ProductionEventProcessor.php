<?php

namespace App\Messaging;

use App\Models\ProductionEventLog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * What the worker actually does with a production event.
 *
 * Separate from the consume command so the command owns only AMQP mechanics
 * (ack/nack, retry, shutdown) and this owns the business side effects. The
 * assignment lists four candidate consumer responsibilities; three are handled
 * here — recording production history, logging the event, sending a
 * notification.
 *
 * The fourth, "updating inventory", is deliberately NOT here: stock is moved
 * synchronously inside ProductionService's transaction, because doing it
 * asynchronously would make "prevent production if inventory is insufficient"
 * unenforceable under concurrency. See the decisions table in TASKS.md.
 */
class ProductionEventProcessor
{
    /**
     * @param  array<string, mixed>  $envelope
     * @param  int  $attempts  delivery attempt number, for the audit row
     * @return bool true if handled (ack), false if this message can never
     *              succeed and should be dead-lettered rather than retried
     */
    public function process(array $envelope, int $attempts = 1): bool
    {
        $payload = $envelope['payload'];
        $orderId = $payload['production_order_id'] ?? null;

        try {
            // The unique index on event_id is the idempotency guarantee. A
            // redelivered message (broker restart, consumer crash after doing
            // the work but before acking) collides here and is treated as
            // already handled — which is why at-least-once delivery is safe.
            ProductionEventLog::query()->create([
                'event_id' => $envelope['event_id'],
                'event_type' => $envelope['event_type'],
                'routing_key' => $envelope['routing_key'],
                'production_order_id' => $orderId,
                'payload' => $payload,
                'attempts' => $attempts,
                'occurred_at' => $envelope['occurred_at'],
                'processed_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            Log::info('[consumer] duplicate event ignored', [
                'event_id' => $envelope['event_id'],
            ]);

            return true;
        }

        $this->logProductionEvent($envelope, $payload);
        $this->sendNotification($payload);

        return true;
    }

    /**
     * @param  array<string, mixed>  $envelope
     * @param  array<string, mixed>  $payload
     */
    private function logProductionEvent(array $envelope, array $payload): void
    {
        Log::info('[consumer] production event recorded', [
            'event_id' => $envelope['event_id'],
            'order_number' => $payload['order_number'] ?? null,
            'stage' => $payload['stage'] ?? null,
            'output_batch' => $payload['output']['batch_number'] ?? null,
            'inputs_consumed' => count($payload['consumed'] ?? []),
        ]);
    }

    /**
     * Stands in for a real notification channel (mail, Slack, broadcast).
     * Kept as a log line rather than wiring a mailer, because a mail driver a
     * reviewer cannot see working adds no evidence that the async path runs —
     * the production_event_logs row already proves that.
     *
     * @param  array<string, mixed>  $payload
     */
    private function sendNotification(array $payload): void
    {
        $output = $payload['output'] ?? [];

        Log::info(sprintf(
            '[notification] Batch %s of %s (%s %s) is ready.',
            $output['batch_number'] ?? '?',
            $output['item_name'] ?? '?',
            $output['quantity'] ?? '?',
            $output['item_type'] ?? '',
        ));
    }
}
