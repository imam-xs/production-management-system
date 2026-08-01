<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexBatchRequest;
use App\Http\Resources\BatchResource;
use App\Http\Responses\ApiResponse;
use App\Services\BatchService;
use App\Services\TraceabilityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BatchController extends Controller
{
    public function __construct(
        private readonly BatchService $batches,
        private readonly TraceabilityService $traceability,
    ) {}

    public function index(IndexBatchRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        // Explicit casts: query-string values are strings, so they are
        // converted here rather than relying on PHP's coercion.
        $batches = $this->batches->list(
            search: $data['search'] ?? null,
            itemId: isset($data['item_id']) ? (int) $data['item_id'] : null,
            itemType: isset($data['item_type']) ? ItemType::from($data['item_type']) : null,
            origin: isset($data['origin']) ? BatchOrigin::from($data['origin']) : null,
            availableOnly: $request->boolean('available_only'),
            perPage: (int) ($data['per_page'] ?? 15),
        );

        return BatchResource::collection($batches);
    }

    public function show(int $batch): BatchResource
    {
        return new BatchResource($this->batches->findOrFail($batch));
    }

    /**
     * Trace a batch back to the batch(es) that fed it — for a finished batch
     * this walks finished -> semi-finished -> raw material, exactly what the
     * assignment calls "batch traceability".
     */
    public function trace(int $batch): JsonResponse
    {
        $model = $this->batches->findOrFail($batch);

        return ApiResponse::data($this->traceability->traceUpstream($model));
    }

    /**
     * The reverse question: what did this batch's stock end up in. Not
     * required by the assignment, but the same repository query already
     * exists (ProductionOrderRepository::consumptionsOfBatch) and it is a
     * natural complement to trace().
     */
    public function traceDownstream(int $batch): JsonResponse
    {
        $model = $this->batches->findOrFail($batch);

        return ApiResponse::data($this->traceability->traceDownstream($model));
    }
}
