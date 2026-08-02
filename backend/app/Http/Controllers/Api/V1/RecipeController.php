<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeLineResource;
use App\Repositories\Contracts\BillOfMaterialRepositoryInterface;
use App\Repositories\Contracts\ItemRepositoryInterface;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipeController extends Controller
{
    public function __construct(
        private readonly BillOfMaterialRepositoryInterface $boms,
        private readonly ItemRepositoryInterface $items,
    ) {}

    // recipe lookup for a given item
    public function show(int $item): AnonymousResourceCollection
    {
        $model = $this->items->findByIdOrFail($item);

        return RecipeLineResource::collection($this->boms->recipeFor($model));
    }
}
