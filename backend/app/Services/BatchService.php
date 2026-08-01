<?php

namespace App\Services;

use App\Enums\BatchOrigin;
use App\Enums\ItemType;
use App\Models\Batch;
use App\Repositories\Contracts\BatchRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Batches as a listable resource — separate from TraceabilityService, which
 * owns the recursive trace algorithm and nothing else. This class has one
 * reason to change ("how batches are listed/fetched"); that one has another
 * ("how the consumption graph is walked").
 */
class BatchService
{
    public function __construct(
        private readonly BatchRepositoryInterface $batches,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Batch>
     */
    public function list(
        ?string $search = null,
        ?int $itemId = null,
        ?ItemType $itemType = null,
        ?BatchOrigin $origin = null,
        bool $availableOnly = false,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->batches->paginate($search, $itemId, $itemType, $origin, $availableOnly, $perPage);
    }

    /**
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findOrFail(int $id): Batch
    {
        return $this->batches->findByIdOrFail($id);
    }
}
