<?php

namespace App\Messaging;

/**
 * Publishes a domain event onto the message bus.
 *
 * Behind an interface for a concrete reason, not ceremony: the API and the
 * test/local paths need different behaviour. `MESSAGE_PUBLISHER=null` lets the
 * whole application run with no broker at all, which is what keeps a missing
 * RabbitMQ from blocking development.
 */
interface MessagePublisherInterface
{
    /**
     * Wrap the payload in the standard envelope and publish it.
     *
     * @param  string  $routingKey  e.g. production.raw_to_semi.completed
     * @param  string  $eventType  logical name, e.g. production.completed
     * @param  array<string, mixed>  $payload
     * @return string the generated event_id, for correlation in logs
     */
    public function publish(string $routingKey, string $eventType, array $payload): string;
}
