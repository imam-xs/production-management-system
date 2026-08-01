<?php

namespace App\Messaging;

use Illuminate\Support\Facades\Log;

/**
 * Writes the envelope to the log instead of a broker.
 *
 * Useful for inspecting exactly what would go on the wire without running
 * RabbitMQ. Selected with MESSAGE_PUBLISHER=log.
 */
class LogPublisher implements MessagePublisherInterface
{
    public function publish(string $routingKey, string $eventType, array $payload): string
    {
        $envelope = EventEnvelope::build($routingKey, $eventType, $payload);

        Log::info('[message-bus] would publish', $envelope);

        return $envelope['event_id'];
    }
}
