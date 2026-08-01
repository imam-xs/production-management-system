<?php

namespace App\Messaging;

use PhpAmqpLib\Message\AMQPMessage;

/**
 * Publishes production events to RabbitMQ over AMQP.
 *
 * Two durability choices worth naming:
 *
 *  - `delivery_mode = 2` (persistent) so a broker restart does not lose
 *    messages sitting in the queue.
 *  - publisher confirms (`confirm_select` + `wait_for_pending_acks`) so
 *    `publish()` only returns once the broker has actually accepted the
 *    message. Without confirms, `basic_publish` is fire-and-forget — it
 *    succeeds locally even if the broker never received anything.
 */
class RabbitMqPublisher implements MessagePublisherInterface
{
    private bool $confirmsEnabled = false;

    public function __construct(
        private readonly RabbitMqConnector $connector,
        private readonly int $confirmTimeout = 5,
    ) {}

    public function publish(string $routingKey, string $eventType, array $payload): string
    {
        $envelope = EventEnvelope::build($routingKey, $eventType, $payload);

        $channel = $this->connector->channel();

        if (! $this->confirmsEnabled) {
            $channel->confirm_select();
            $this->confirmsEnabled = true;
        }

        $message = new AMQPMessage(
            (string) json_encode($envelope, JSON_THROW_ON_ERROR),
            [
                'content_type' => 'application/json',
                'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
                'message_id' => $envelope['event_id'],
                'type' => $eventType,
                'timestamp' => now()->getTimestamp(),
                'application_headers' => new \PhpAmqpLib\Wire\AMQPTable([
                    'x-attempts' => 1,
                ]),
            ],
        );

        $channel->basic_publish($message, $this->connector->exchangeName(), $routingKey);

        // Blocks until the broker confirms, so a failure surfaces here rather
        // than being silently swallowed.
        $channel->wait_for_pending_acks($this->confirmTimeout);

        return $envelope['event_id'];
    }
}
