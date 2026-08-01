<?php

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/**
 * Steel fabrication catalogue — the domain from the assignment brief.
 *
 *   Steel sheets / zinc / rods (raw)
 *     -> steel rods, galvanised sheets (semi-finished)
 *        -> steel pipes, steel frames (finished)
 */
class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $units = Unit::query()->pluck('id', 'code');

        $items = [
            // Raw materials — enter inventory by purchase receipt.
            ['sku' => 'RAW-STEEL-SHEET', 'name' => 'Cold Rolled Steel Sheet', 'type' => ItemType::Raw, 'unit' => 'kg', 'reorder_level' => 500],
            ['sku' => 'RAW-ZINC-INGOT', 'name' => 'Zinc Ingot', 'type' => ItemType::Raw, 'unit' => 'kg', 'reorder_level' => 100],
            ['sku' => 'RAW-WELD-ROD', 'name' => 'Welding Rod', 'type' => ItemType::Raw, 'unit' => 'pcs', 'reorder_level' => 200],

            // Semi-finished — produced from raw materials.
            ['sku' => 'SEMI-STEEL-ROD', 'name' => 'Steel Rod', 'type' => ItemType::SemiFinished, 'unit' => 'pcs', 'reorder_level' => 150],
            ['sku' => 'SEMI-GALV-SHEET', 'name' => 'Galvanised Steel Sheet', 'type' => ItemType::SemiFinished, 'unit' => 'sheet', 'reorder_level' => 80],

            // Finished goods — produced from semi-finished products.
            ['sku' => 'FIN-STEEL-PIPE', 'name' => 'Steel Pipe', 'type' => ItemType::Finished, 'unit' => 'pcs', 'reorder_level' => 50],
            ['sku' => 'FIN-STEEL-FRAME', 'name' => 'Steel Support Frame', 'type' => ItemType::Finished, 'unit' => 'pcs', 'reorder_level' => 25],
        ];

        foreach ($items as $item) {
            Item::query()->updateOrCreate(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'type' => $item['type'],
                    'unit_id' => $units[$item['unit']],
                    'reorder_level' => $item['reorder_level'],
                    'is_active' => true,
                ],
            );
        }
    }
}
