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

    //production history track complete production history
    public function index(IndexProductionOrderRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        // query-string values are strings
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

   
     //plan a run — stage and inputs are derived server-side, not supplied by the client. inventory is untouched until execute()
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

    //execute a pending order: consume its recipe's inputs, produce one output batch
    public function execute(int $productionOrder): ProductionOrderResource
    {
        $order = $this->production->findOrFail($productionOrder);
        $completed = $this->production->execute($order);

        return new ProductionOrderResource($this->production->findOrFail($completed->id));
    }
}
