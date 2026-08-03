<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// the traceability edge: which input batch, and how much of it, a production order consumed
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_consumptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('production_order_id')->constrained('production_orders')->cascadeOnDelete();
            $table->foreignId('input_batch_id')->constrained('batches')->restrictOnDelete();
            $table->decimal('quantity_consumed', 15, 4);
            $table->timestamps();

            $table->unique(['production_order_id', 'input_batch_id'], 'consumptions_order_batch_unique');

            // downstream trace: "what did this batch end up in?"
            $table->index('input_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_consumptions');
    }
};
