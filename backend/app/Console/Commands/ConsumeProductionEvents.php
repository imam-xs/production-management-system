<?php

namespace App\Console\Commands;

use App\Messaging\EventEnvelope;
use App\Messaging\ProductionEventProcessor;
use App\Messaging\RabbitMqConnector;
use Illuminate\Console\Command;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * The asynchronous half of the system — a long-running worker, separate from
 * any HTTP request, that drains production events off RabbitMQ.
 *
 * This is what the `worker` service in docker-compose.yml runs. It shares the
 * application code (and therefore the models and topology definition) with the
 * API, but nothing else: the API never touches this queue, and this process
 * never serves a request.
 *
 * Delivery semantics implemented here:
 *
 *  - manual acknowledgement — a message is only removed once the work
 *    committed, so a crash mid-handler results in redelivery, not loss
 *  - prefetch (QoS) — caps unacked messages in flight so one worker cannot
 *    hoard the queue
 *  - bounded retry — attempts are counted in an AMQP header and the message is
 *    republished; past the limit it is rejected without requeue and the
 *    dead-letter exchange routes it to the DLQ
 *  - graceful shutdown — SIGTERM/SIGINT finish the in-flight message before
 *    exiting, which is why docker-compose gives it a 30s stop_grace_period
 */
class ConsumeProductionEvents extends Command
{
    protected $signature = 'rabbitmq:consume
                            {--max-messages=0 : Stop after N messages (0 = run forever)}
                            {--timeout=0 : Stop after N seconds of inactivity (0 = wait forever)}';

    protected $description = 'Consume production events from RabbitMQ and record them asynchronously';

    private bool $shouldStop = false;

    private int $handled = 0;

    public function handle(RabbitMqConnector $connector, ProductionEventProcessor $processor): int
    {
        $this->listenForShutdownSignals();

        $maxMessages = (int) $this->option('max-messages');
        $idleTimeout = (int) $this->option('timeout');
        $maxAttempts = (int) config('rabbitmq.consumer.max_attempts');
        $prefetch = (int) config('rabbitmq.consumer.prefetch');

        try {
            $channel = $connector->channel();
        } catch (Throwable $e) {
            $this->components->error('Cannot reach RabbitMQ: '.$e->getMessage());

            return self::FAILURE;
        }

        $channel->basic_qos(0, $prefetch, false);

        $queue = $connector->queueName();

        $this->components->info("Consuming from [{$queue}] — prefetch {$prefetch}, max attempts {$maxAttempts}");

        $channel->basic_consume(
            $queue,
            consumer_tag: 'pms-worker-'.gethostname(),
            no_local: false,
            no_ack: false,          // manual ack: see class docblock
            exclusive: false,
            nowait: false,
            callback: function (AMQPMessage $message) use ($processor, $connector, $maxAttempts, $maxMessages): void {
                $this->handleMessage($message, $processor, $connector, $maxAttempts);

                $this->handled++;

                if ($maxMessages > 0 && $this->handled >= $maxMessages) {
                    $this->shouldStop = true;
                }
            },
        );

        // Never block indefinitely. php-amqplib can only service AMQP heartbeats
        // while it is inside a *bounded* read, so an unbounded wait() leaves the
        // connection silent until the broker declares it dead — the worker then
        // exits with "Missed server heartbeat" and is only rescued by Docker's
        // restart policy. Waking at half the heartbeat interval keeps the
        // connection alive through arbitrarily long idle periods.
        $heartbeat = (int) config('rabbitmq.connection.heartbeat');
        $tick = $heartbeat > 0 ? max(1, intdiv($heartbeat, 2)) : 5;
        $lastActivity = time();

        while ($channel->is_consuming() && ! $this->shouldStop) {
            try {
                $channel->wait(null, false, $tick);
                $lastActivity = time();
            } catch (AMQPTimeoutException) {
                // A quiet tick, not a failure — keep waiting. --timeout bounds a
                // test run by measuring genuine inactivity rather than by the
                // length of any single wait.
                if ($idleTimeout > 0 && (time() - $lastActivity) >= $idleTimeout) {
                    break;
                }
            } catch (Throwable $e) {
                $this->components->error('Consumer loop error: '.$e->getMessage());
                break;
            }
        }

        $connector->close();

        $this->components->info("Stopped after handling {$this->handled} message(s).");

        return self::SUCCESS;
    }

    private function handleMessage(
        AMQPMessage $message,
        ProductionEventProcessor $processor,
        RabbitMqConnector $connector,
        int $maxAttempts,
    ): void {
        $attempts = $this->attemptsOf($message);

        $decoded = json_decode($message->getBody(), true);
        $envelope = EventEnvelope::validate($decoded);

        // A malformed message can never succeed, so retrying it would only
        // burn attempts — reject straight to the DLQ.
        if ($envelope === null) {
            $this->components->warn('Malformed message → DLQ');
            $message->nack(requeue: false);

            return;
        }

        try {
            $handled = $processor->process($envelope, $attempts);

            if ($handled) {
                $message->ack();
                $this->line("  <fg=green>✓</> {$envelope['event_type']} {$envelope['event_id']}");

                return;
            }

            // Handler judged this permanently unprocessable.
            $message->nack(requeue: false);
        } catch (Throwable $e) {
            $this->handleFailure($message, $connector, $envelope, $attempts, $maxAttempts, $e);
        }
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function handleFailure(
        AMQPMessage $message,
        RabbitMqConnector $connector,
        array $envelope,
        int $attempts,
        int $maxAttempts,
        Throwable $e,
    ): void {
        if ($attempts >= $maxAttempts) {
            $this->components->error("Giving up after {$attempts} attempt(s) → DLQ: {$e->getMessage()}");

            // requeue=false is what triggers the dead-letter exchange.
            $message->nack(requeue: false);

            return;
        }

        $this->components->warn("Attempt {$attempts} failed, republishing: {$e->getMessage()}");

        // Republish with an incremented counter rather than nack(requeue=true),
        // which would put the message straight back at the head of the queue and
        // spin hot. A delayed retry would need the delayed-message plugin;
        // immediate re-publish is the trade-off taken here.
        $retry = new AMQPMessage($message->getBody(), [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'message_id' => $envelope['event_id'],
            'type' => $envelope['event_type'],
            'application_headers' => new AMQPTable(['x-attempts' => $attempts + 1]),
        ]);

        $connector->channel()->basic_publish(
            $retry,
            $connector->exchangeName(),
            (string) $envelope['routing_key'],
        );

        $message->ack();
    }

    private function attemptsOf(AMQPMessage $message): int
    {
        try {
            $headers = $message->get('application_headers');

            if ($headers instanceof AMQPTable) {
                $native = $headers->getNativeData();

                return (int) ($native['x-attempts'] ?? 1);
            }
        } catch (Throwable) {
            // No headers on the message — treat as first attempt.
        }

        return 1;
    }

    /**
     * pcntl is compiled into the container image precisely for this. Without a
     * clean shutdown, `docker compose down` would kill the worker mid-message
     * and rely on redelivery; with it, the in-flight message is finished first.
     */
    private function listenForShutdownSignals(): void
    {
        if (! extension_loaded('pcntl')) {
            return;
        }

        pcntl_async_signals(true);

        foreach ([SIGTERM, SIGINT] as $signal) {
            pcntl_signal($signal, function (): void {
                $this->components->warn('Shutdown signal received — finishing current message.');
                $this->shouldStop = true;
            });
        }
    }
}
