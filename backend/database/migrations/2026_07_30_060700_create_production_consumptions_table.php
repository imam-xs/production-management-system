<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The traceability edge: which input batch, and how much of it, a production
 * order consumed.
 *
 * This single table answers both relationships the assignment asks for — which
 * raw material batches went into a semi-finished batch, and which semi-finished
 * batches went into a finished batch — because the edge is stage-agnostic:
 *
 *   finished batch
 *     -> production_order            (batches.production_order_id)
 *     -> production_consumptions     (input_batch_id ...)
 *     -> semi-finished batches
 *          -> production_order       (recurse)
 *          -> raw material batches   (origin = purchase, recursion terminates)
 *
 * The input item is deliberately *not* duplicated here — it is reachable via
 * `input_batch_id -> batches.item_id`, and storing it again would allow the two
 * to disagree.
 */
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

            // A run may draw from the same batch only once — the allocator
            // aggregates per batch before writing. Named explicitly because the
            // generated name would exceed MySQL's 64-character identifier limit.
            $table->unique(['production_order_id', 'input_batch_id'], 'consumptions_order_batch_unique');

            // Downstream trace: "what did this batch end up in?"
            $table->index('input_batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_consumptions');
    }
};
