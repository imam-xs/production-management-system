<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Current on-hand quantity per item — a read cache, not the source of truth.
 *
 * The authoritative figures are `batches.quantity_remaining` (per batch) and the
 * `inventory_movements` ledger (per event). This table exists so the inventory
 * endpoints answer in one indexed row-read instead of aggregating the ledger,
 * and it is written inside the same transaction as the movements it summarises,
 * so it can never drift. `InventoryService` can re-derive it from the ledger,
 * which is what makes the denormalisation safe to defend.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_stocks', function (Blueprint $table): void {
            $table->foreignId('item_id')->primary()->constrained('items')->cascadeOnDelete();
            $table->decimal('quantity_on_hand', 15, 4)->default(0);
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_stocks');
    }
};
