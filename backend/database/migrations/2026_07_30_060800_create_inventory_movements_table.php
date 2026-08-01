<?php

use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only stock ledger — the audit source of truth.
 *
 * Every quantity change writes exactly one row here, inside the transaction
 * that made the change. Nothing updates or deletes rows in this table, so
 * summing it per item must always equal `item_stocks.quantity_on_hand`; that
 * invariant is what lets the cache be trusted and rebuilt.
 *
 * `quantity` is signed (negative for consumption) and `balance_after` records
 * the running total at the time of the movement, so history can be read without
 * replaying every prior row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // Adjustments may be item-level; every other type is batch-specific.
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();

            $table->enum('type', MovementType::values());
            $table->decimal('quantity', 15, 4);          // signed
            $table->decimal('balance_after', 15, 4);

            // What caused this movement — a production order, a receipt, etc.
            $table->nullableMorphs('reference');

            $table->text('note')->nullable();
            $table->timestamps();

            // Per-item history, newest first.
            $table->index(['item_id', 'created_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
