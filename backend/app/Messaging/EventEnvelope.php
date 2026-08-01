<?php

namespace App\Messaging;

use Illuminate\Support\Str;

/**
 * The wire format every message on the bus uses.
 *
 * Built in one place and read in one place so the publisher and the consumer
 * cannot drift apart on key names. Versioned from the start: a consumer can
 * branch on `version` when the payload shape changes, instead of having to
 * guess whether a field will be present.
 *
 *   {
 *     "event_id":    "9f1c...",              unique — the consumer's idempotency key
 *     "event_type":  "production.completed",
 *     "version":     1,
 *     "routing_key": "production.raw_to_semi.completed",
 *     "occurred_at": "2026-07-30T09:15:00+00:00",
 *     "payload":     { ... }
 *   }
 */
class EventEnvelope
{
    public const VERSION = 1;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function build(string $routingKey, string $eventType, array $payload): array
    {
        return [
            'event_id' => (string) Str::uuid(),
            'event_type' => $eventType,
            'version' => self::VERSION,
            'routing_key' => $routingKey,
            'occurred_at' => now()->toIso8601String(),
            'payload' => $payload,
        ];
    }

    /**
     * Validate a decoded message before the consumer acts on it. A malformed
     * message is a permanent failure — retrying it can never help — so the
     * consumer sends it straight to the DLQ rather than looping.
     *
     * @return array<string, mixed>|null null when the shape is unusable
     */
    public static function validate(mixed $decoded): ?array
    {
        if (! is_array($decoded)) {
            return null;
        }

        foreach (['event_id', 'event_type', 'routing_key', 'occurred_at', 'payload'] as $key) {
            if (! array_key_exists($key, $decoded)) {
                return null;
            }
        }

        if (! is_array($decoded['payload'])) {
            return null;
        }

        return $decoded;
    }
}
