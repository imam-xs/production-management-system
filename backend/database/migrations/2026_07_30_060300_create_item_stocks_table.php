<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
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
