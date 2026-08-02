<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ItemFilterRequest;
use App\Http\Resources\ItemResource;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

abstract class ItemController extends Controller
{
    public function __construct(
        protected readonly ItemService $items,
    ) {}

    abstract protected function itemType(): ItemType;

    protected function doIndex(ItemFilterRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        $paginated = $this->items->list(
            $this->itemType(),
            search: $data['search'] ?? null,
            isActive: isset($data['is_active']) ? $request->boolean('is_active') : null,
            sortBy: $data['sort_by'] ?? 'name',
            sortDirection: $data['sort_direction'] ?? 'asc',
            perPage: (int) ($data['per_page'] ?? 15),
        );

        return ItemResource::collection($paginated);
    }

    protected function doShow(int $id): ItemResource
    {
        return new ItemResource($this->items->findOrFail($id, $this->itemType()));
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function doStore(array $validated): JsonResponse
    {
        $item = $this->items->create($this->itemType(), $validated);

        return (new ItemResource($item))->response()->setStatusCode(201);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function doUpdate(int $id, array $validated): JsonResponse
    {
        $item = $this->items->findOrFail($id, $this->itemType());
        $updated = $this->items->update($item, $validated);

        return (new ItemResource($updated))->response();
    }

    protected function doDestroy(int $id): JsonResponse
    {
        $item = $this->items->findOrFail($id, $this->itemType());
        $this->items->delete($item);

        return response()->json(status: 204);
    }
}
