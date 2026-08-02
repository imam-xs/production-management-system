<?php

namespace App\Repositories\Contracts;

use App\Models\BillOfMaterial;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

// recipe lookups
interface BillOfMaterialRepositoryInterface
{
    /**
     * @return Collection<int, BillOfMaterial>
     */
    public function recipeFor(Item $outputItem): Collection;

    public function hasRecipe(Item $outputItem): bool;

    public function isReferenced(Item $item): bool;
}
