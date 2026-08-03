<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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

            // one line per input per output
            $table->unique(['output_item_id', 'input_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_of_materials');
    }
};
