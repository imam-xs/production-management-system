<?php

namespace App\Repositories\Eloquent;

use App\Models\BillOfMaterialModel;
use App\Models\ItemModel;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class BillOfMaterialRepository implements BillOfMaterialRepositoryInterface
{
    public function recipeFor(ItemModel $outputItem): Collection
    {
        return BillOfMaterialModel::query()
            ->with(['inputItem.unit', 'inputItem.stock'])
            ->where('output_item_id', $outputItem->id)
            ->orderBy('input_item_id')
            ->get();
    }

    public function hasRecipe(ItemModel $outputItem): bool
    {
        return BillOfMaterialModel::query()->where('output_item_id', $outputItem->id)->exists();
    }

    public function isReferenced(ItemModel $item): bool
    {
        return BillOfMaterialModel::query()
            ->where('output_item_id', $item->id)
            ->orWhere('input_item_id', $item->id)
            ->exists();
    }
}
