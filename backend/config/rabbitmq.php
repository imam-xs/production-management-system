<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Publisher implementation
    |---------------------------------------------------------------------------
    |
    | Which MessagePublisherInterface binding the container resolves. Keeping
    | this configurable means the test suite and local development never need a
    | running broker: "null" discards events, "log" writes them to the log.
    |
    */

    'publisher' => env('MESSAGE_PUBLISHER', 'rabbitmq'),

    /*
    |---------------------------------------------------------------------------
    | Connection
    |---------------------------------------------------------------------------
    */

    'connection' => [
        'host' => env('RABBITMQ_HOST', 'rabbitmq'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
        'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 10),
        'read_write_timeout' => (float) env('RABBITMQ_HEARTBEAT', 60) * 2,
        'heartbeat' => (int) env('RABBITMQ_HEARTBEAT', 60),
        'keepalive' => true,
    ],

    /*
    |---------------------------------------------------------------------------
    | Topology
    |---------------------------------------------------------------------------
    |
    | Declared idempotently before publishing and before consuming, so the
    | broker needs no manual setup and the stack is reproducible from scratch.
    |
    |   production.events  (topic, durable)
    |     └─ production.events.processing   binding: production.*.completed
    |          └─ dead letters ──> production.events.dlx ──> production.events.dlq
    |
    */

    'exchange' => env('RABBITMQ_EXCHANGE', 'production.events'),

    'queue' => env('RABBITMQ_QUEUE', 'production.events.processing'),

    'binding_key' => 'production.*.completed',

    'dead_letter' => [
        'exchange' => env('RABBITMQ_DLX', 'production.events.dlx'),
        'queue' => env('RABBITMQ_DLQ', 'production.events.dlq'),
        'routing_key' => 'production.failed',
    ],

    /*
    |---------------------------------------------------------------------------
    | Consumer
    |---------------------------------------------------------------------------
    |
    | prefetch     — unacked messages allowed in flight per consumer.
    | max_attempts — redeliveries before a message is routed to the DLQ.
    |
    */

    'consumer' => [
        'prefetch' => (int) env('RABBITMQ_PREFETCH', 10),
        'max_attempts' => (int) env('RABBITMQ_MAX_ATTEMPTS', 3),
    ],

];
