<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recipe: how much of each input one unit of an output requires.
 *
 * This is what turns "prevent production if inventory is insufficient" into a
 * calculable rule — required quantity is `quantity_per_unit * planned_quantity`
 * rather than something the API caller asserts. It also means a production
 * request only needs an output item and a quantity; the inputs are derived.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_of_materials', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('output_item_id')->constrained('items')->cascadeOnDelete();
            $table->foreignId('input_item_id')->constrained('items')->restrictOnDelete();
            $table->decimal('quantity_per_unit', 15, 4);
            $table->timestamps();

            // One line per input per output.
            $table->unique(['output_item_id', 'input_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_of_materials');
    }
};
