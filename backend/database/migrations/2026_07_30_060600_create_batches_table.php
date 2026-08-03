<?php

use App\Enums\BatchOrigin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// a uniquely identifiable lot of a single item
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

            // null for purchased batches, set for manufactured ones
            $table->foreignId('production_order_id')->nullable()
                ->constrained('production_orders')->nullOnDelete();

            $table->timestamp('produced_at');
            $table->timestamps();

            // FIFO allocation: available batches of an item, oldest first
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
