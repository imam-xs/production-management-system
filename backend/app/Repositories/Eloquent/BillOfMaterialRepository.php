<?php

namespace App\Repositories\Eloquent;

use App\Models\BillOfMaterial;
use App\Models\Item;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BillOfMaterialRepository implements BillOfMaterialRepositoryInterface
{
    public function recipeFor(Item $outputItem): Collection
    {
        return BillOfMaterial::query()
            ->with(['inputItem.unit', 'inputItem.stock'])
            ->where('output_item_id', $outputItem->id)
            ->orderBy('input_item_id')
            ->get();
    }

    public function hasRecipe(Item $outputItem): bool
    {
        return BillOfMaterial::query()->where('output_item_id', $outputItem->id)->exists();
    }

    public function isReferenced(Item $item): bool
    {
        return BillOfMaterial::query()
            ->where('output_item_id', $item->id)
            ->orWhere('input_item_id', $item->id)
            ->exists();
    }
}
