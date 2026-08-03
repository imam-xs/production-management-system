<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// what the RabbitMQ consumer writes — proof the asynchronous path ran
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
