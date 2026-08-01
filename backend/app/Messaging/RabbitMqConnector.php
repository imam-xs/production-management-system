<?php

namespace App\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Owns the AMQP connection and declares the topology.
 *
 * Shared by the publisher (API process) and the consumer (worker process) so
 * both agree on the exact exchange/queue/binding definitions. Declaring from
 * code rather than by hand is what makes the broker reproducible: `docker
 * compose down -v && up` recreates an empty RabbitMQ and the topology reappears
 * on first use, with no manual setup step in the README.
 *
 * All declarations are idempotent — AMQP returns success if an entity already
 * exists with identical arguments — so calling this on every boot is safe.
 *
 * Topology:
 *
 *   production.events                (topic exchange, durable)
 *     │  binding: production.*.completed
 *     ▼
 *   production.events.processing     (durable queue)
 *     │  on nack(requeue=false) or TTL/length limits
 *     ▼
 *   production.events.dlx            (topic exchange, durable)
 *     │  binding: production.failed
 *     ▼
 *   production.events.dlq            (durable queue — poison messages land here)
 */
class RabbitMqConnector
{
    private ?AMQPStreamConnection $connection = null;

    private mixed $channel = null;

    private bool $topologyDeclared = false;

    /**
     * @param  array<string, mixed>  $config  the `rabbitmq` config array
     */
    public function __construct(
        private readonly array $config,
    ) {}

    /**
     * A live channel with the topology guaranteed to exist.
     */
    public function channel(): mixed
    {
        if ($this->channel === null) {
            $this->connect();
        }

        if (! $this->topologyDeclared) {
            $this->declareTopology();
            $this->topologyDeclared = true;
        }

        return $this->channel;
    }

    public function queueName(): string
    {
        return (string) $this->config['queue'];
    }

    public function exchangeName(): string
    {
        return (string) $this->config['exchange'];
    }

    public function deadLetterRoutingKey(): string
    {
        return (string) $this->config['dead_letter']['routing_key'];
    }

    public function close(): void
    {
        $this->channel?->close();
        $this->connection?->close();

        $this->channel = null;
        $this->connection = null;
        $this->topologyDeclared = false;
    }

    private function connect(): void
    {
        $c = $this->config['connection'];

        $this->connection = new AMQPStreamConnection(
            host: (string) $c['host'],
            port: (int) $c['port'],
            user: (string) $c['user'],
            password: (string) $c['password'],
            vhost: (string) $c['vhost'],
            connection_timeout: (float) $c['connection_timeout'],
            read_write_timeout: (float) $c['read_write_timeout'],
            keepalive: (bool) $c['keepalive'],
            heartbeat: (int) $c['heartbeat'],
        );

        $this->channel = $this->connection->channel();
    }

    private function declareTopology(): void
    {
        $exchange = $this->exchangeName();
        $queue = $this->queueName();
        $dlx = (string) $this->config['dead_letter']['exchange'];
        $dlq = (string) $this->config['dead_letter']['queue'];
        $dlRoutingKey = $this->deadLetterRoutingKey();

        // Main exchange. Topic, so one binding pattern covers every production
        // stage and adding a stage needs no topology change.
        $this->channel->exchange_declare($exchange, 'topic', false, true, false);

        // Dead-letter exchange must exist before the queue that references it.
        $this->channel->exchange_declare($dlx, 'topic', false, true, false);

        // Work queue, wired to dead-letter anything rejected without requeue.
        $this->channel->queue_declare($queue, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => $dlx,
            'x-dead-letter-routing-key' => $dlRoutingKey,
        ]));

        $this->channel->queue_bind($queue, $exchange, (string) $this->config['binding_key']);

        // Poison-message queue.
        $this->channel->queue_declare($dlq, false, true, false, false);
        $this->channel->queue_bind($dlq, $dlx, $dlRoutingKey);
    }
}
