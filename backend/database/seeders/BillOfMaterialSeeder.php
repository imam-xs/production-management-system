<?php

namespace Database\Seeders;

use App\Models\BillOfMaterialModel;
use App\Models\ItemModel;
use Illuminate\Database\Seeder;

class BillOfMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $items = ItemModel::query()->pluck('id', 'sku');

        $recipes = [
            // raw -> semi-finished
            ['output' => 'SEMI-STEEL-ROD', 'input' => 'RAW-STEEL-SHEET', 'qty' => 2.5],
            ['output' => 'SEMI-GALV-SHEET', 'input' => 'RAW-STEEL-SHEET', 'qty' => 8.0],
            ['output' => 'SEMI-GALV-SHEET', 'input' => 'RAW-ZINC-INGOT', 'qty' => 0.4],

            // semi-finished -> finished
            ['output' => 'FIN-STEEL-PIPE', 'input' => 'SEMI-STEEL-ROD', 'qty' => 1.5],
            ['output' => 'FIN-STEEL-FRAME', 'input' => 'SEMI-STEEL-ROD', 'qty' => 4.0],
            ['output' => 'FIN-STEEL-FRAME', 'input' => 'SEMI-GALV-SHEET', 'qty' => 2.0],
        ];

        foreach ($recipes as $recipe) {
            BillOfMaterialModel::query()->updateOrCreate(
                [
                    'output_item_id' => $items[$recipe['output']],
                    'input_item_id' => $items[$recipe['input']],
                ],
                ['quantity_per_unit' => $recipe['qty']],
            );
        }
    }
}
