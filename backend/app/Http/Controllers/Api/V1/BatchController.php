<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Http\Controllers\Controller;
use App\Http\Requests\BatchFilterRequest;
use App\Http\Resources\BatchResource;
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

    public function index(BatchFilterRequest $request): AnonymousResourceCollection
    {
        $data = $request->validated();

        // explicit casts: query-string values are strings, so they are
        // converted here rather than relying on PHP's coercion
        $batches = $this->batches->list([
            'search' => $data['search'] ?? null,
            'item_type' => isset($data['item_type']) ? ItemType::from($data['item_type']) : null,
            'origin' => isset($data['origin']) ? BatchOrigin::from($data['origin']) : null,
            'available_only' => $request->boolean('available_only'),
        ], perPage: (int) ($data['per_page'] ?? 15));

        return BatchResource::collection($batches);
    }

    public function show(int $batch): BatchResource
    {
        return new BatchResource($this->batches->findOrFail($batch));
    }

    public function trace(int $batch): JsonResponse
    {
        $model = $this->batches->findOrFail($batch);

        return $this->data($this->traceability->traceUpstream($model));
    }

    public function traceDownstream(int $batch): JsonResponse
    {
        $model = $this->batches->findOrFail($batch);

        return $this->data($this->traceability->traceDownstream($model));
    }
}
