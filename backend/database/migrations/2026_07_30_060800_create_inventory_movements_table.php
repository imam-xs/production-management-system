<?php

use App\Enums\MovementType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();

            // adjustments may be item-level, every other type is batch-specific
            $table->foreignId('batch_id')->nullable()->constrained('batches')->nullOnDelete();

            $table->enum('type', MovementType::values());
            $table->decimal('quantity', 15, 4);          // signed
            $table->decimal('balance_after', 15, 4);

            // what caused this movement — a production order, a receipt, etc
            $table->nullableMorphs('reference');

            $table->text('note')->nullable();
            $table->timestamps();

            // per item history, newest first
            $table->index(['item_id', 'created_at']);
            $table->index('batch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
