<?php

namespace App\Listeners;

use App\Events\ProductionCompleted;
use App\Jobs\RecordProductionCompleted;
use Illuminate\Support\Facades\Log;
use Throwable;

// turns the domain event into a queued job on the RabbitMQ connection
class PublishProductionCompleted
{
    public function handle(ProductionCompleted $event): void
    {
        $order = $event->order;
        $job = RecordProductionCompleted::for($order);

        try {
            dispatch($job);

            Log::info('[message-bus] queued production.completed', [
                'event_id' => $job->eventId,
                'order_number' => $order->order_number,
            ]);
        } catch (Throwable $e) {
            Log::error('[message-bus] dispatch failed — production itself was committed', [
                'order_number' => $order->order_number,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
