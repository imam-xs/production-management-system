<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexItemRequest;
use App\Http\Resources\ItemResource;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Shared CRUD logic for the three item-stage controllers.
 *
 * Not abstract-method-per-action: Laravel resolves a FormRequest by the exact
 * class named in the controller method's signature, so `store()`/`update()`
 * can't be defined once here with an abstract Request type. Each concrete
 * controller keeps a one-line action method wiring its own Store/Update
 * Request class to these `do*` helpers, which hold the actual logic.
 */
abstract class ItemController extends Controller
{
    public function __construct(
        protected readonly ItemService $items,
    ) {}

    abstract protected function itemType(): ItemType;

    protected function doIndex(IndexItemRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        // Query-string values always arrive as strings, so they are cast
        // explicitly rather than relying on PHP's coercion. `is_active`
        // distinguishes "absent, don't filter" (null) from an explicit
        // true/false.
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
