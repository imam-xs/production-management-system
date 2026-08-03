<?php

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Models\ItemModel;
use App\Models\UnitModel;
use Illuminate\Database\Seeder;

// steel fabrication catalogue
class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $units = UnitModel::query()->pluck('id', 'code');

        $items = [
            // raw materials — enter inventory by purchase receipt
            ['sku' => 'RAW-STEEL-SHEET', 'name' => 'Cold Rolled Steel Sheet', 'type' => ItemType::Raw, 'unit' => 'kg', 'reorder_level' => 500],
            ['sku' => 'RAW-ZINC-INGOT', 'name' => 'Zinc Ingot', 'type' => ItemType::Raw, 'unit' => 'kg', 'reorder_level' => 100],
            ['sku' => 'RAW-WELD-ROD', 'name' => 'Welding Rod', 'type' => ItemType::Raw, 'unit' => 'pcs', 'reorder_level' => 200],

            // semi-finished — produced from raw materials
            ['sku' => 'SEMI-STEEL-ROD', 'name' => 'Steel Rod', 'type' => ItemType::SemiFinished, 'unit' => 'pcs', 'reorder_level' => 150],
            ['sku' => 'SEMI-GALV-SHEET', 'name' => 'Galvanised Steel Sheet', 'type' => ItemType::SemiFinished, 'unit' => 'sheet', 'reorder_level' => 80],

            // finished goods — produced from semi-finished products
            ['sku' => 'FIN-STEEL-PIPE', 'name' => 'Steel Pipe', 'type' => ItemType::Finished, 'unit' => 'pcs', 'reorder_level' => 50],
            ['sku' => 'FIN-STEEL-FRAME', 'name' => 'Steel Support Frame', 'type' => ItemType::Finished, 'unit' => 'pcs', 'reorder_level' => 25],
        ];

        foreach ($items as $item) {
            ItemModel::query()->updateOrCreate(
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
