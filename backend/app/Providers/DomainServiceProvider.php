<?php

namespace App\Providers;

use App\Messaging\LogPublisher;
use App\Messaging\MessagePublisherInterface;
use App\Messaging\NullPublisher;
use App\Messaging\RabbitMqConnector;
use App\Messaging\RabbitMqPublisher;
use App\Services\BatchNumberGenerator;
use App\Services\BatchNumberGeneratorInterface;
use App\Services\OrderNumberGenerator;
use App\Services\OrderNumberGeneratorInterface;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Bindings for the domain services and the message bus.
 *
 * Most of App\Services is resolved by the container as concrete classes —
 * controllers are their only consumer and the repository interfaces beneath
 * them already provide the seam that matters. Only three things are bound to
 * interfaces here, each because a second implementation genuinely exists or is
 * genuinely needed: the two numbering schemes, and the publisher.
 */
class DomainServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(BatchNumberGeneratorInterface::class, BatchNumberGenerator::class);
        $this->app->singleton(OrderNumberGeneratorInterface::class, OrderNumberGenerator::class);

        // One connector per process. The publisher (API) and the consumer
        // (worker) each get their own instance in their own process, but within
        // a process the AMQP connection is reused rather than reopened.
        $this->app->singleton(RabbitMqConnector::class, fn (): RabbitMqConnector => new RabbitMqConnector(
            config('rabbitmq'),
        ));

        $this->app->singleton(MessagePublisherInterface::class, function (): MessagePublisherInterface {
            $driver = (string) config('rabbitmq.publisher');

            return match ($driver) {
                'rabbitmq' => new RabbitMqPublisher($this->app->make(RabbitMqConnector::class)),
                'log' => new LogPublisher,
                'null' => new NullPublisher,
                default => throw new InvalidArgumentException(
                    "Unsupported MESSAGE_PUBLISHER [{$driver}]. Expected: rabbitmq, log, null.",
                ),
            };
        });
    }

    // No event/listener registration here on purpose. Laravel 11+ auto-discovers
    // listeners in app/Listeners by their typed handle() argument, so
    // PublishProductionCompleted is already wired to ProductionCompleted.
    // Registering it explicitly as well makes the listener fire twice — which
    // published every production event to RabbitMQ two times. Confirm the wiring
    // with `php artisan event:list` rather than by adding it back.
}
