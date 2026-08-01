<?php

namespace App\Services;

use App\Models\Batch;
use App\Repositories\Contracts\ProductionOrderRepositoryInterface;

/**
 * Walks the consumption graph a production batch sits in.
 *
 * `traceUpstream` answers the assignment's core requirement — from a finished
 * batch, back to the semi-finished batch(es) used, and from those to the
 * originating raw material batch(es) — as one stage-agnostic recursion rather
 * than branching per level, because `production_consumptions` records the same
 * kind of edge (order consumed batch, this much) regardless of which two stages
 * it connects.
 *
 * `traceDownstream` answers the reverse question for any batch: what did this
 * end up in?
 */
class TraceabilityService
{
    /**
     * Recursion depth guard. Two production stages means a legitimate trace
     * never exceeds depth 2 (finished -> semi -> raw); this catches a data
     * error growing the walk unbounded rather than letting it run away.
     */
    private const MAX_DEPTH = 10;

    public function __construct(
        private readonly ProductionOrderRepositoryInterface $orders,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function traceUpstream(Batch $batch, int $depth = 0): array
    {
        $node = $this->batchNode($batch);

        if (! $batch->origin->isTraceable() || $depth >= self::MAX_DEPTH) {
            // A purchased batch is a leaf: it originated outside the plant,
            // so there is nothing further upstream to walk.
            return $node;
        }

        if ($batch->productionOrder === null) {
            return $node;
        }

        $consumptions = $this->orders->consumptionsWithBatches($batch->productionOrder);

        $node['consumed'] = $consumptions->map(fn ($consumption): array => [
            'quantity_consumed' => (string) $consumption->quantity_consumed,
            'batch' => $this->traceUpstream($consumption->inputBatch, $depth + 1),
        ])->all();

        return $node;
    }

    /**
     * Where a batch's stock ended up: every production order that consumed
     * part of it, and the output batch each of those orders produced.
     *
     * @return array<string, mixed>
     */
    public function traceDownstream(Batch $batch, int $depth = 0): array
    {
        $node = $this->batchNode($batch);

        if ($depth >= self::MAX_DEPTH) {
            return $node;
        }

        $consumptions = $this->orders->consumptionsOfBatch($batch);

        $node['used_in'] = $consumptions->map(function ($consumption) use ($depth): array {
            $outputBatch = $consumption->productionOrder->outputBatch;

            return [
                'quantity_consumed' => (string) $consumption->quantity_consumed,
                'order_number' => $consumption->productionOrder->order_number,
                'output_batch' => $outputBatch === null ? null : $this->traceDownstream($outputBatch, $depth + 1),
            ];
        })->all();

        return $node;
    }

    /**
     * @return array<string, mixed>
     */
    private function batchNode(Batch $batch): array
    {
        return [
            'batch_number' => $batch->batch_number,
            'item' => [
                'sku' => $batch->item->sku,
                'name' => $batch->item->name,
                'type' => $batch->item->type->value,
            ],
            'quantity_produced' => (string) $batch->quantity_produced,
            'quantity_remaining' => (string) $batch->quantity_remaining,
            'origin' => $batch->origin->value,
            'production_order_number' => $batch->productionOrder?->order_number,
            'produced_at' => $batch->produced_at->toIso8601String(),
        ];
    }
}
