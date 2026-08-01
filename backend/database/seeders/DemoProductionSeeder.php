<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ProductionOrder;
use App\Services\InventoryService;
use App\Services\ProductionService;
use Illuminate\Database\Seeder;

/**
 * Seeds a complete, traceable two-stage production chain by running real
 * production through InventoryService and ProductionService — not by
 * hand-inserting batch/consumption rows.
 *
 * That distinction matters: if this seeder built rows directly, a broken
 * ProductionService could still leave the demo data looking correct. Routing
 * it through the actual services means a reviewer opening the traceability
 * endpoint on a freshly seeded stack is looking at proof the domain logic
 * works, not a fixture.
 *
 * Quantities below are chosen so every step clears the bill-of-materials
 * requirement with room to spare — see the arithmetic in each comment. To
 * demonstrate the insufficient-inventory rejection instead, request more of
 * an output than remaining stock supports (README has a ready-made example).
 */
class DemoProductionSeeder extends Seeder
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ProductionService $production,
    ) {}

    public function run(): void
    {
        // Every other seeder is updateOrCreate and so is safe to re-run; this one
        // creates receipts and production runs, which would stack up. It has to
        // be guarded because the container entrypoint calls `migrate --seed` on
        // every boot, and Laravel runs the seeders whether or not a migration was
        // actually pending — so a plain `docker compose restart` would otherwise
        // double the demo quantities.
        //
        // Any production order at all is enough to stop: either this seeder has
        // already run, or the database is in use and must not be added to.
        if (ProductionOrder::query()->exists()) {
            $this->command->info('  Demo chain already present — skipped.');

            return;
        }

        $sheet = Item::query()->where('sku', 'RAW-STEEL-SHEET')->firstOrFail();
        $zinc = Item::query()->where('sku', 'RAW-ZINC-INGOT')->firstOrFail();
        $rod = Item::query()->where('sku', 'SEMI-STEEL-ROD')->firstOrFail();
        $galvSheet = Item::query()->where('sku', 'SEMI-GALV-SHEET')->firstOrFail();
        $pipe = Item::query()->where('sku', 'FIN-STEEL-PIPE')->firstOrFail();
        $frame = Item::query()->where('sku', 'FIN-STEEL-FRAME')->firstOrFail();

        // Receive raw materials: 2000 kg sheet, 300 kg zinc.
        $this->inventory->receive($sheet, '2000', now()->subDays(5));
        $this->inventory->receive($zinc, '300', now()->subDays(5));

        // rod needs 2.5 kg sheet/unit -> 150 rods costs 375 kg (2000 -> 1625 remaining).
        $rodOrder = $this->production->createOrder($rod, '150');
        $this->production->execute($rodOrder);

        // galvanised sheet needs 8 kg sheet + 0.4 kg zinc/unit -> 30 sheets costs
        // 240 kg sheet (1625 -> 1385) and 12 kg zinc (300 -> 288).
        $galvOrder = $this->production->createOrder($galvSheet, '30');
        $this->production->execute($galvOrder);

        // pipe needs 1.5 rods/unit -> 40 pipes costs 60 rods (150 -> 90 remaining).
        $pipeOrder = $this->production->createOrder($pipe, '40');
        $this->production->execute($pipeOrder);

        // frame needs 4 rods + 2 galv sheets/unit -> 10 frames costs 40 rods
        // (90 -> 50) and 20 galv sheets (30 -> 10).
        $frameOrder = $this->production->createOrder($frame, '10');
        $this->production->execute($frameOrder);

        // Second pipe run, drawing on the same rod batch as the first — shows
        // one input batch feeding multiple downstream orders. Needs 22.5 rods
        // (50 -> 27.5 remaining).
        $secondPipeOrder = $this->production->createOrder($pipe, '15');
        $this->production->execute($secondPipeOrder);

        $this->command->info('  Demo chain: 2 raw receipts, 2 semi-finished runs, 3 finished-goods runs — all traceable end to end.');
    }
}
