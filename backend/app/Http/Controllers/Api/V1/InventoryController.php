<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReceiveStockRequest;
use App\Http\Resources\BatchResource;
use App\Http\Resources\InventoryMovementResource;
use App\Http\Resources\InventoryStockResource;
use App\Http\Resources\ItemResource;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Services\InventoryService;
use App\Services\ItemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly ItemService $items,
        private readonly ItemRepositoryInterface $itemsRepository,
    ) {}

    /**
     * Current inventory across every stage — "view current inventory at every
     * production stage".
     */
    public function index(): AnonymousResourceCollection
    {
        return InventoryStockResource::collection($this->inventory->snapshot());
    }

    /**
     * Current inventory for one stage only.
     */
    public function byStage(string $stage): AnonymousResourceCollection
    {
        return InventoryStockResource::collection($this->inventory->snapshot($this->resolveStage($stage)));
    }

    public function lowStock(): AnonymousResourceCollection
    {
        return ItemResource::collection($this->items->lowStock());
    }

    /**
     * Receive a quantity of a raw material into a new purchase batch — the
     * only way raw-material stock increases.
     */
    public function receive(ReceiveStockRequest $request): JsonResponse
    {
        $data = $request->validated();
        $item = $this->itemsRepository->findByIdOrFail((int) $data['item_id']);

        $batch = $this->inventory->receive(
            $item,
            (string) $data['quantity'],
            isset($data['produced_at']) ? new \DateTimeImmutable($data['produced_at']) : null,
            $data['note'] ?? null,
        );

        return (new BatchResource($batch->load(['item.unit', 'productionOrder'])))->response()->setStatusCode(201);
    }

    /**
     * The full inventory ledger for one item — any of the three stages.
     */
    public function movements(int $item): AnonymousResourceCollection
    {
        $model = $this->itemsRepository->findByIdOrFail($item);

        return InventoryMovementResource::collection($this->inventory->movementsFor($model));
    }

    private function resolveStage(string $value): ItemType
    {
        return ItemType::tryFrom($value) ?? throw new NotFoundHttpException("Unknown production stage \"{$value}\".");
    }
}
