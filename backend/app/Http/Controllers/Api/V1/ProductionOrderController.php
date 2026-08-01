<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionStage;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexProductionOrderRequest;
use App\Http\Requests\StoreProductionOrderRequest;
use App\Http\Resources\ProductionOrderResource;
use App\Repositories\Contracts\ItemRepositoryInterface;
use App\Services\ProductionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductionOrderController extends Controller
{
    public function __construct(
        private readonly ProductionService $production,
        private readonly ItemRepositoryInterface $items,
    ) {}

    /**
     * Production history — "track complete production history".
     */
    public function index(IndexProductionOrderRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        // Explicit casts: query-string values are strings, so they are
        // converted here rather than relying on PHP's coercion.
        $orders = $this->production->list(
            search: $data['search'] ?? null,
            stage: isset($data['stage']) ? ProductionStage::from($data['stage']) : null,
            status: isset($data['status']) ? ProductionOrderStatus::from($data['status']) : null,
            outputItemId: isset($data['output_item_id']) ? (int) $data['output_item_id'] : null,
            perPage: (int) ($data['per_page'] ?? 15),
        );

        return ProductionOrderResource::collection($orders);
    }

    public function show(int $productionOrder): ProductionOrderResource
    {
        return new ProductionOrderResource($this->production->findOrFail($productionOrder));
    }

    /**
     * Plan a run — stage and inputs are derived server-side, not supplied by
     * the client. Inventory is untouched until execute().
     */
    public function store(StoreProductionOrderRequest $request): JsonResponse
    {
        $data = $request->validated();
        $outputItem = $this->items->findByIdOrFail((int) $data['output_item_id']);

        $order = $this->production->createOrder(
            $outputItem,
            (string) $data['planned_quantity'],
            $request->user(),
        );

        return (new ProductionOrderResource($order))->response()->setStatusCode(201);
    }

    /**
     * Execute a pending order: consume its recipe's inputs, produce one output
     * batch. The endpoint the assignment calls "production execution (batch
     * creation)".
     */
    public function execute(int $productionOrder): ProductionOrderResource
    {
        $order = $this->production->findOrFail($productionOrder);
        $completed = $this->production->execute($order);

        // execute() re-fetches the order via lockById(), which deliberately
        // skips eager loading (a locking read should touch exactly the row it
        // locks). Re-fetching here through the fully-loaded path is what lets
        // the response include the output batch and consumption edges instead
        // of silently omitting them.
        return new ProductionOrderResource($this->production->findOrFail($completed->id));
    }
}
