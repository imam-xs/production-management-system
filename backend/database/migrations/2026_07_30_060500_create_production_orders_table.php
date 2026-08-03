<?php

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('order_number', 32)->unique();
            $table->enum('stage', ProductionStage::values());
            $table->foreignId('output_item_id')->constrained('items')->restrictOnDelete();

            $table->decimal('planned_quantity', 15, 4);
            $table->decimal('produced_quantity', 15, 4)->default(0);

            $table->enum('status', ProductionOrderStatus::values())
                ->default(ProductionOrderStatus::Pending->value);

            // set when execution succeeds, null while pending
            $table->timestamp('completed_at')->nullable();

            // why a run failed, surfaced in the production history
            $table->text('failure_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // history listings filter by status/stage and sort by recency
            $table->index(['status', 'stage']);
            $table->index('output_item_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
