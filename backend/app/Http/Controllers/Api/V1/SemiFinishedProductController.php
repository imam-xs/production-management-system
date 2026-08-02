<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Requests\ItemFilterRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SemiFinishedProductController extends ItemController
{
    protected function itemType(): ItemType
    {
        return ItemType::SemiFinished;
    }

    public function index(ItemFilterRequest $request): AnonymousResourceCollection
    {
        return $this->doIndex($request);
    }

    public function show(int $semiFinishedProduct): ItemResource
    {
        return $this->doShow($semiFinishedProduct);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        return $this->doStore($request->validated());
    }

    public function update(UpdateItemRequest $request, int $semiFinishedProduct): JsonResponse
    {
        return $this->doUpdate($semiFinishedProduct, $request->validated());
    }

    public function destroy(int $semiFinishedProduct): JsonResponse
    {
        return $this->doDestroy($semiFinishedProduct);
    }
}
