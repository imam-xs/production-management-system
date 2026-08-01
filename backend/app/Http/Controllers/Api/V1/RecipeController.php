<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeLineResource;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only view of a product's bill of materials.
 *
 * Deliberately has no store/update/destroy. Recipes define what the plant is
 * capable of building and are treated as setup rather than operational data;
 * editing them safely would need cycle prevention, stage-order validation and
 * versioning (a recipe change must not rewrite what past batches actually
 * consumed). None of that is in scope, so the API exposes the recipe for
 * inspection only — see the README's design notes.
 */
class RecipeController extends Controller
{
    public function __construct(
        private readonly BillOfMaterialRepositoryInterface $boms,
        private readonly ItemRepositoryInterface $items,
    ) {}

    /**
     * The recipe for one produced item. Raw materials are purchased rather than
     * built, so theirs is legitimately empty.
     */
    public function show(int $item): AnonymousResourceCollection
    {
        $model = $this->items->findByIdOrFail($item);

        return RecipeLineResource::collection($this->boms->recipeFor($model));
    }
}
