<?php

namespace App\Repositories\Contracts;

use App\Models\ItemModel;
use Illuminate\Database\Eloquent\Collection;

// recipe lookups
interface BillOfMaterialRepositoryInterface
{
    public function recipeFor(ItemModel $outputItem): Collection;

    public function hasRecipe(ItemModel $outputItem): bool;

    public function isReferenced(ItemModel $item): bool;
}
