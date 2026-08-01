<?php

use App\Enums\ItemType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every material and product in the plant, discriminated by `type`.
 *
 * Raw materials, semi-finished products and finished products live here
 * together because they are structurally identical — each has an SKU, a unit,
 * batches, stock and movements. The `type` column is what keeps their
 * inventories independent and drives which REST resource exposes them.
 *
 * The alternative (three parallel tables) would force three copies of the
 * batch, stock, movement and consumption tables, and would make the
 * traceability walk branch on type at every level instead of recursing
 * uniformly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 64)->unique();
            $table->string('name');
            $table->enum('type', ItemType::values());
            $table->foreignId('unit_id')->constrained('units')->restrictOnDelete();
            $table->text('description')->nullable();

            // Threshold for the low-stock report; not a hard production guard.
            $table->decimal('reorder_level', 15, 4)->default(0);

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Listing endpoints always filter by type, usually by active flag.
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
