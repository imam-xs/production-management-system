<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the RabbitMQ consumer writes — proof the asynchronous path ran.
 *
 * `event_id` is the publisher-generated UUID carried in the message envelope and
 * is unique here, which is what makes the consumer idempotent: a redelivered
 * message hits the unique index and is acknowledged instead of double-processing.
 *
 * This table is the visible difference between a real event-driven path and a
 * synchronous call wearing a costume — rows only appear once a separate process
 * has consumed the message.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_event_logs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('event_id')->unique();
            $table->string('event_type', 64);
            $table->string('routing_key', 128);

            $table->foreignId('production_order_id')->nullable()
                ->constrained('production_orders')->nullOnDelete();

            $table->json('payload');

            $table->unsignedTinyInteger('attempts')->default(1);

            // dateTime, not timestamp: MySQL/MariaDB auto-assign an implicit
            // default to the *first* TIMESTAMP column in a table and leave any
            // subsequent NOT NULL one with '0000-00-00', which strict mode
            // rejects. MySQL 8 hides this behind explicit_defaults_for_timestamp
            // being ON by default; MariaDB 10.4 has it OFF and errors (1067).
            // dateTime has no such magic and behaves identically on both.
            $table->dateTime('occurred_at');       // when the domain event happened
            $table->dateTime('processed_at');      // when the consumer handled it

            $table->timestamps();

            $table->index('production_order_id');
            $table->index('event_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_event_logs');
    }
};
