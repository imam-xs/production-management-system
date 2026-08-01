<?php

namespace Database\Seeders;

use App\Models\BillOfMaterial;
use App\Models\Item;
use Illuminate\Database\Seeder;

/**
 * Recipes for both production stages.
 *
 * Quantities are per one unit of output, so a run for 40 steel rods requires
 * 40 * 2.5 = 100 kg of steel sheet — that multiplication is the whole basis of
 * the "insufficient inventory" check.
 */
class BillOfMaterialSeeder extends Seeder
{
    public function run(): void
    {
        $items = Item::query()->pluck('id', 'sku');

        $recipes = [
            // Raw -> semi-finished
            ['output' => 'SEMI-STEEL-ROD', 'input' => 'RAW-STEEL-SHEET', 'qty' => 2.5],
            ['output' => 'SEMI-GALV-SHEET', 'input' => 'RAW-STEEL-SHEET', 'qty' => 8.0],
            ['output' => 'SEMI-GALV-SHEET', 'input' => 'RAW-ZINC-INGOT', 'qty' => 0.4],

            // Semi-finished -> finished
            ['output' => 'FIN-STEEL-PIPE', 'input' => 'SEMI-STEEL-ROD', 'qty' => 1.5],
            ['output' => 'FIN-STEEL-FRAME', 'input' => 'SEMI-STEEL-ROD', 'qty' => 4.0],
            ['output' => 'FIN-STEEL-FRAME', 'input' => 'SEMI-GALV-SHEET', 'qty' => 2.0],
        ];

        foreach ($recipes as $recipe) {
            BillOfMaterial::query()->updateOrCreate(
                [
                    'output_item_id' => $items[$recipe['output']],
                    'input_item_id' => $items[$recipe['input']],
                ],
                ['quantity_per_unit' => $recipe['qty']],
            );
        }
    }
}
