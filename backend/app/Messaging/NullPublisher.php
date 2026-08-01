<?php

namespace App\Messaging;

use Illuminate\Support\Str;

/**
 * Discards events entirely. Selected with MESSAGE_PUBLISHER=null so the
 * application can run with no broker available at all.
 */
class NullPublisher implements MessagePublisherInterface
{
    public function publish(string $routingKey, string $eventType, array $payload): string
    {
        return (string) Str::uuid();
    }
}
