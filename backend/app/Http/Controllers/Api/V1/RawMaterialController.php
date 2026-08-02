<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Requests\IndexItemRequest;
use App\Http\Requests\StoreItemRequest;
use App\Http\Requests\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RawMaterialController extends ItemController
{
    protected function itemType(): ItemType
    {
        return ItemType::Raw;
    }

    public function index(IndexItemRequest $request): AnonymousResourceCollection
    {
        return $this->doIndex($request);
    }

    public function show(int $rawMaterial): ItemResource
    {
        return $this->doShow($rawMaterial);
    }

    public function store(StoreItemRequest $request): JsonResponse
    {
        return $this->doStore($request->validated());
    }

    public function update(UpdateItemRequest $request, int $rawMaterial): JsonResponse
    {
        return $this->doUpdate($rawMaterial, $request->validated());
    }

    public function destroy(int $rawMaterial): JsonResponse
    {
        return $this->doDestroy($rawMaterial);
    }
}
