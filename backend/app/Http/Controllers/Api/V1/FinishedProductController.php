<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Requests\IndexItemRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinishedProductController extends ItemController
{
    protected function itemType(): ItemType
    {
        return ItemType::Finished;
    }

    public function index(IndexItemRequest $request): AnonymousResourceCollection
    {
        return $this->doIndex($request);
    }

    public function show(int $finishedProduct): ItemResource
    {
        return $this->doShow($finishedProduct);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        return $this->doStore($request->validated());
    }

    public function update(UpdateItemRequest $request, int $finishedProduct): JsonResponse
    {
        return $this->doUpdate($finishedProduct, $request->validated());
    }

    public function destroy(int $finishedProduct): JsonResponse
    {
        return $this->doDestroy($finishedProduct);
    }
}
