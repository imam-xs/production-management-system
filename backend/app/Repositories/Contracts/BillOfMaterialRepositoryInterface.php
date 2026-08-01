<?php

namespace App\Repositories\Contracts;

use App\Models\BillOfMaterial;
use App\Models\Item;
use Illuminate\Database\Eloquent\Collection;

/**
 * Recipe lookups.
 *
 * Separate from ItemRepository because the consumer is different: production
 * asks "what does this output require?", never "list me some items". Keeping the
 * two apart is the interface-segregation half of the design — ProductionService
 * depends only on this, and knows nothing about item CRUD.
 */
interface BillOfMaterialRepositoryInterface
{
    /**
     * Recipe lines for an output item, with each input item and its unit eager
     * loaded.
     *
     * @return Collection<int, BillOfMaterial>
     */
    public function recipeFor(Item $outputItem): Collection;

    public function hasRecipe(Item $outputItem): bool;

    /**
     * Whether any recipe line names this item, on either side.
     *
     * Deleting an item a recipe depends on would leave that recipe unbuildable,
     * so deletion has to refuse while the reference exists.
     */
    public function isReferenced(Item $item): bool;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): BillOfMaterial;

    public function delete(BillOfMaterial $line): void;
}
