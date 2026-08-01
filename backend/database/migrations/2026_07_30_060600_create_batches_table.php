<?php

use App\Enums\BatchOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A uniquely identifiable lot of a single item.
 *
 * Stock is tracked here, per batch, not as one running total per item — that is
 * what makes traceability possible: consuming stock means consuming *specific*
 * batches, and those identities are what the trace walks back through.
 *
 *   quantity_produced  — never changes; what this batch originally contained.
 *   quantity_remaining — decremented as the batch is consumed downstream.
 *
 * A Purchase batch is a traceability leaf. A Production batch always carries the
 * order that made it, giving the recursion its next hop.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 32)->unique();
            $table->foreignId('item_id')->constrained('items')->restrictOnDelete();

            $table->decimal('quantity_produced', 15, 4);
            $table->decimal('quantity_remaining', 15, 4);

            $table->enum('origin', BatchOrigin::values());

            // Null for purchased batches; set for manufactured ones.
            $table->foreignId('production_order_id')->nullable()
                ->constrained('production_orders')->nullOnDelete();

            $table->timestamp('produced_at');
            $table->timestamps();

            // FIFO allocation: available batches of an item, oldest first.
            $table->index(['item_id', 'quantity_remaining']);
            $table->index(['item_id', 'produced_at']);
            $table->index('production_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('batches');
    }
};
